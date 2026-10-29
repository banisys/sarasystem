<?php

namespace Modules\Contract\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSender;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Contract\Enums\SignatureMethod;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Services\ContractReminders;
use Modules\Contract\Services\Contracts;
use Modules\Contract\Tests\Concerns\MakesContracts;
use Modules\Employee\Models\Employee;
use Tests\Concerns\InteractsWithTenants;
use Tests\Fakes\FakeSmsSender;
use Tests\TestCase;

/**
 * Daily reminders of contracts about to end or waiting for a signature
 * (ADR 0021).
 */
class ContractRemindersTest extends TestCase
{
    use InteractsWithTenants, MakesContracts, RefreshDatabase;

    private const MOBILE = '09121234567';

    private Tenant $primary;

    private Tenant $branch;

    private User $clerk;

    private ContractType $type;

    private Employee $employee;

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2027, 1, 10)->setTime(8, 0));

        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);

        $this->primary = Tenant::factory()->create();
        $this->primary->makePrimary();
        $this->branch = Tenant::factory()->create(['name' => 'شعبه شمال']);

        $this->roleWithPermissions('contracts_clerk', ['ViewAny:Contract', 'Update:Contract']);
        $this->clerk = User::factory()->create();
        $this->grantRole($this->clerk, $this->branch, 'contracts_clerk');

        $this->type = $this->contractType($this->primary, ['title' => 'قرارداد کارمند', 'signature_method' => SignatureMethod::Electronic]);
        $this->employee = $this->employeeIn($this->branch, User::factory()->create(['mobile' => self::MOBILE]));
    }

    public function test_staff_of_the_tenant_are_told_once_when_a_contract_is_about_to_end(): void
    {
        $ending = $this->signed('2027-02-01');
        $this->signed('2027-06-01');
        $otherClerk = User::factory()->create();
        $this->grantRole($otherClerk, $this->primary, 'contracts_clerk');

        $this->artisan('contracts:send-reminders')->assertSuccessful();
        $this->artisan('contracts:send-reminders')->assertSuccessful();

        $this->assertSame(1, $this->clerk->notifications()->count());
        $this->assertStringContainsString('قرارداد کارمند', $this->clerk->notifications()->first()->data['body']);
        $this->assertSame(0, $otherClerk->notifications()->count());
        $this->assertNotNull($ending->refresh()->expiry_reminded_at);
    }

    public function test_a_renewed_contract_or_an_amendment_is_not_reminded_about(): void
    {
        $renewed = $this->signed('2027-02-01');
        app(Contracts::class)->draftRenewal($renewed, $this->type, ['starts_on' => '2027-02-02'], null);

        $amendmentType = $this->contractType($this->primary, ['is_amendment' => true]);
        $amendment = app(Contracts::class)->draftAmendment($this->signed('2027-06-01'), $amendmentType, ['starts_on' => '2027-01-15', 'ends_on' => '2027-02-01'], null);
        app(Contracts::class)->signElectronically(app(Contracts::class)->issue($amendment, null), $this->employee->user, null);

        app(ContractReminders::class)->expiring();

        $this->assertSame(0, $this->clerk->notifications()->count());
    }

    public function test_the_person_is_reminded_by_sms_to_sign_a_few_times(): void
    {
        $contract = $this->issuedFor($this->employee, $this->type);
        $this->sms->sent = [];

        $this->artisan('contracts:send-reminders');
        $this->assertSame([], $this->sms->sent, 'Not on the day it is issued.');

        foreach ([3, 6, 9, 12] as $days) {
            $this->travelTo(now()->setDate(2027, 1, 10 + $days)->setTime(8, 0));
            $this->artisan('contracts:send-reminders');
        }

        $this->assertCount(3, $this->sms->sent);
        $this->assertSame(self::MOBILE, $this->sms->sent[0]['mobile']);
        $this->assertStringContainsString('هنوز امضا نشده', $this->sms->sent[0]['message']);
        $this->assertSame(3, $contract->refresh()->signature_reminder_count);
    }

    public function test_no_sms_for_a_signed_or_paper_only_contract(): void
    {
        $this->signed('2027-12-01');
        $this->issuedFor($this->employee, $this->contractType($this->primary, ['signature_method' => SignatureMethod::Paper]));
        $this->sms->sent = [];

        $this->travelTo(now()->addDays(5));
        $this->artisan('contracts:send-reminders');

        $this->assertSame([], $this->sms->sent);
    }

    public function test_the_reminders_run_daily(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, 'contracts:send-reminders'));

        $this->assertNotNull($event);
        $this->assertSame('0 8 * * *', $event->expression);
    }

    private function signed(string $endsOn): Contract
    {
        $contract = app(Contracts::class)->issue($this->draftFor($this->employee, $this->type, ['starts_on' => '2026-03-21', 'ends_on' => $endsOn]), null);

        return app(Contracts::class)->signElectronically($contract, $this->employee->user, null);
    }
}
