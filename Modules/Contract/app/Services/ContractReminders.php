<?php

namespace Modules\Contract\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Sms\SmsSender;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Modules\Contract\Enums\ContractKind;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Enums\SignatureMethod;
use Modules\Contract\Filament\Resources\Contracts\ContractResource;
use Modules\Contract\Models\Contract;
use Spatie\Permission\Models\Permission;

/**
 * Daily reminders (ADR 0021): staff are told once when a signed contract
 * is about to end and has no renewal; the person gets an SMS while an
 * issued contract they may sign electronically waits for them.
 */
class ContractReminders
{
    /** Staff who may issue and renew contracts get the expiry reminders. */
    public const STAFF_PERMISSION = 'Update:Contract';

    public function __construct(private SmsSender $sms) {}

    /**
     * @return int how many contracts were reminded about
     */
    public function expiring(): int
    {
        $contracts = Contract::withoutTenantScope()
            ->with(['contractType', 'contractable', 'tenant'])
            ->where('status', ContractStatus::Signed)
            ->where('kind', '!=', ContractKind::Amendment)
            ->whereNull('expiry_reminded_at')
            ->whereDate('ends_on', '>=', today())
            ->whereDate('ends_on', '<=', today()->addDays((int) config('contract.reminders.expiring_within_days')))
            ->whereDoesntHave('renewals', fn (Builder $renewal) => $renewal->where('status', '!=', ContractStatus::Cancelled))
            ->get();

        foreach ($contracts as $contract) {
            $tenant = $contract->tenant;
            $notification = Notification::make()
                ->title('قرارداد رو به پایان')
                ->body("«{$contract->contractType->title}» {$contract->personName()} ({$contract->number}) در ".ContractRenderer::date($contract->ends_on)." تمام می‌شود و هنوز تمدید نشده است — {$tenant->name}")
                ->icon('heroicon-o-clock')
                ->warning()
                ->actions([
                    Action::make('open')
                        ->label('قراردادها')
                        ->url(ContractResource::getUrl('index', panel: 'admin', tenant: $tenant))
                        ->markAsRead(),
                ]);

            foreach ($this->staffOf($tenant, $contract) as $user) {
                $user->notifyNow($notification->toDatabase());
            }

            $contract->forceFill(['expiry_reminded_at' => now()])->save();
        }

        return $contracts->count();
    }

    /**
     * @return int how many people were sent an SMS
     */
    public function unsigned(): int
    {
        $due = now()->subDays((int) config('contract.reminders.unsigned_every_days'));

        $contracts = Contract::withoutTenantScope()
            ->with(['contractType', 'user', 'tenant'])
            ->where('status', ContractStatus::Issued)
            ->where('signature_reminder_count', '<', (int) config('contract.reminders.unsigned_max'))
            ->where(fn (Builder $query) => $query
                ->where('signature_reminded_at', '<=', $due)
                ->orWhere(fn (Builder $query) => $query->whereNull('signature_reminded_at')->where('issued_at', '<=', $due)))
            ->whereHas('contractType', fn (Builder $type) => $type->where('signature_method', '!=', SignatureMethod::Paper))
            ->get()
            ->filter(fn (Contract $contract): bool => filled($contract->user?->mobile));

        foreach ($contracts as $contract) {
            $this->sms->send($contract->user->mobile, "یادآوری: قرارداد «{$contract->contractType->title}» ({$contract->tenant->name}) هنوز امضا نشده است. برای خواندن و امضای آن وارد ".config('app.name').' شوید.');

            $contract->forceFill([
                'signature_reminded_at' => now(),
                'signature_reminder_count' => $contract->signature_reminder_count + 1,
            ])->save();
        }

        return $contracts->count();
    }

    /**
     * Holders of a role with the staff permission in the tenant, and the
     * contract's issuer.
     *
     * @return Collection<int, User>
     */
    private function staffOf(Tenant $tenant, Contract $contract): Collection
    {
        $roleIds = Permission::query()->where('name', self::STAFF_PERMISSION)->first()?->roles()->pluck('id')->all() ?? [];

        return User::query()
            ->where(fn (Builder $query) => $query
                ->whereIn('id', fn (QueryBuilder $roles) => $roles
                    ->select(config('permission.column_names.model_morph_key'))
                    ->from(config('permission.table_names.model_has_roles'))
                    ->where('model_type', (new User)->getMorphClass())
                    ->whereIn('role_id', $roleIds)
                    ->where('tenant_id', $tenant->getKey()))
                ->when($contract->issued_by_id !== null, fn (Builder $query) => $query->orWhereKey($contract->issued_by_id)))
            ->get();
    }
}
