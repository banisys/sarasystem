<?php

namespace Modules\Contract\Database\Seeders;

use App\Models\FormCustomization;
use App\Models\Tenant;
use App\Models\Workflow;
use Database\Seeders\Concerns\GrantsDemoPermissions;
use Illuminate\Database\Seeder;
use Modules\Contract\Enums\SignatureMethod;
use Modules\Contract\Forms\ContractForm;
use Modules\Contract\Models\ContractType;
use Modules\Contract\Workflow\ContractStepExtension;
use RuntimeException;

/**
 * Sample contract types for local development (ADR 0020), on top of the
 * core DemoSeeder and the Employee / Student demo workflows: an employment
 * contract with a monthly wage and working hours, signed either way, a
 * raise amendment of it (ADR 0021), and a tuition contract signed
 * electronically. The step before the last of each
 * demo workflow prepares the contract. The demo roles get the contract
 * permissions. Safe to run again.
 */
class ContractDemoSeeder extends Seeder
{
    use GrantsDemoPermissions;

    public function run(): void
    {
        $headOffice = Tenant::primary() ?? throw new RuntimeException('No primary tenant: run the core seeder first.');

        // Head office staff define types and handle contracts everywhere they
        // work; branch managers prepare, issue and renew their branch's.
        $this->grantDemoPermissions([
            'hq_member' => ['ViewAny:Contract', 'View:Contract', 'Create:Contract', 'Update:Contract', 'Delete:Contract',
                'ViewAny:ContractType', 'View:ContractType', 'Create:ContractType', 'Update:ContractType', 'Delete:ContractType', 'Reorder:ContractType'],
            'principal' => ['ViewAny:Contract', 'View:Contract', 'Create:Contract', 'Update:Contract', 'Delete:Contract'],
        ]);

        $headOffice->execute(function () use ($headOffice): void {
            $employment = $this->type('قرارداد کارمند تمام‌وقت', ['employee'], SignatureMethod::Either, 'ک-', self::EMPLOYMENT_TEXT);
            $this->fields($headOffice, $employment, [
                ['key' => 'fdemowage01', 'label' => 'حقوق ماهانه (ریال)', 'type' => 'number', 'required' => true, 'section' => 'terms'],
                ['key' => 'fdemohours1', 'label' => 'ساعات کار در هفته', 'type' => 'number', 'required' => true, 'section' => 'terms'],
            ]);

            $raise = $this->type('الحاقیه افزایش حقوق', ['employee'], SignatureMethod::Either, null, self::RAISE_TEXT, amendment: true);
            $this->fields($headOffice, $raise, [
                ['key' => 'fdemoraise1', 'label' => 'حقوق ماهانه‌ی جدید (ریال)', 'type' => 'number', 'required' => true, 'section' => 'terms'],
            ], ['ends_on' => 'hidden', 'amount' => 'hidden']);

            $tuition = $this->type('قرارداد شهریه', ['learner'], SignatureMethod::Electronic, 'ش-', self::TUITION_TEXT);

            $this->prepareBeforeLastStep('استخدام کادر آموزشی (نمونه)', $employment);
            $this->prepareBeforeLastStep('پذیرش دانش‌آموز (نمونه)', $tuition);
        });
    }

    /**
     * @param  list<string>  $parties
     */
    private function type(string $title, array $parties, SignatureMethod $method, ?string $prefix, string $body, bool $amendment = false): ContractType
    {
        return ContractType::query()->updateOrCreate(['title' => $title], [
            'parties' => $parties,
            'is_amendment' => $amendment,
            'signature_method' => $method,
            'number_prefix' => $prefix,
            'body' => $body,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, string>  $modes
     */
    private function fields(Tenant $tenant, ContractType $type, array $fields, array $modes = ['ends_on' => 'required', 'amount' => 'hidden']): void
    {
        FormCustomization::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'form' => ContractForm::KEY, 'owner_type' => $type->getMorphClass(), 'owner_id' => $type->id],
            ['field_modes' => $modes, 'fields' => $fields],
        );
    }

    private function prepareBeforeLastStep(string $workflowName, ContractType $type): void
    {
        $workflow = Workflow::query()->where('name', $workflowName)->first();

        if ($workflow === null) {
            return;
        }

        $steps = $workflow->steps;
        $steps[max(0, count($steps) - 2)]['extensions'][ContractStepExtension::KEY] = ['prepare' => [$type->id]];
        $workflow->update(['steps' => $steps]);
    }

    private const EMPLOYMENT_TEXT = <<<'HTML'
        <h2>ماده ۱ - طرفین قرارداد</h2>
        <p>این قرارداد میان <span data-type="mergeTag" data-id="tenant_name"></span> (کارفرما) و <span data-type="mergeTag" data-id="person_name"></span> فرزند <span data-type="mergeTag" data-id="father_name"></span> به کد ملی <span data-type="mergeTag" data-id="national_code"></span>، متولد <span data-type="mergeTag" data-id="birth_date"></span>، به نشانی <span data-type="mergeTag" data-id="address"></span> (کارمند) بسته می‌شود.</p>
        <h2>ماده ۲ - موضوع و مدت</h2>
        <p>کارمند در سمت <span data-type="mergeTag" data-id="job_title"></span> از <span data-type="mergeTag" data-id="starts_on"></span> تا <span data-type="mergeTag" data-id="ends_on"></span> و <span data-type="mergeTag" data-id="fdemohours1"></span> ساعت در هفته کار می‌کند.</p>
        <h2>ماده ۳ - حقوق</h2>
        <p>حقوق ماهانه‌ی کارمند <span data-type="mergeTag" data-id="fdemowage01"></span> ریال است که تا پنجم هر ماه پرداخت می‌شود.</p>
        <h2>ماده ۴ - تعهدات</h2>
        <p>کارمند متعهد است آیین‌نامه‌های داخلی را رعایت کند و اطلاعات فراگیران را محرمانه نگه دارد.</p>
        HTML;

    private const RAISE_TEXT = <<<'HTML'
        <p>به استناد قرارداد شماره‌ی <span data-type="mergeTag" data-id="parent_number"></span> میان <span data-type="mergeTag" data-id="tenant_name"></span> و <span data-type="mergeTag" data-id="person_name"></span>، از <span data-type="mergeTag" data-id="starts_on"></span> حقوق ماهانه‌ی کارمند به <span data-type="mergeTag" data-id="fdemoraise1"></span> ریال تغییر می‌کند. سایر مواد قرارداد بدون تغییر است.</p>
        HTML;

    private const TUITION_TEXT = <<<'HTML'
        <h2>ماده ۱ - طرفین قرارداد</h2>
        <p>این قرارداد میان <span data-type="mergeTag" data-id="tenant_name"></span> و <span data-type="mergeTag" data-id="person_name"></span> فرزند <span data-type="mergeTag" data-id="father_name"></span> به کد ملی <span data-type="mergeTag" data-id="national_code"></span> بسته می‌شود.</p>
        <h2>ماده ۲ - موضوع</h2>
        <p>ثبت‌نام در <span data-type="mergeTag" data-id="program"></span> از <span data-type="mergeTag" data-id="starts_on"></span> تا <span data-type="mergeTag" data-id="ends_on"></span>.</p>
        <h2>ماده ۳ - شهریه</h2>
        <p>شهریه <span data-type="mergeTag" data-id="amount"></span> ریال (<span data-type="mergeTag" data-id="amount_words"></span>) است.</p>
        HTML;
}
