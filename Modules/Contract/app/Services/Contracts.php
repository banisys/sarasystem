<?php

namespace Modules\Contract\Services;

use App\Contract\ContractParties;
use App\CustomFields\ResolvedForm;
use App\Filament\CustomFields\CustomFieldInputs;
use App\Models\User;
use App\Sms\SmsSender;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Enums\SignedVia;
use Modules\Contract\Forms\ContractForm;
use Modules\Contract\Models\Contract;
use Modules\Contract\Models\ContractType;

/**
 * What happens to contracts (ADR 0020): drafts are prepared and changed,
 * issued with their text fixed, signed electronically or on paper, or
 * cancelled; an applicant's contracts move to the member on acceptance.
 * Callers check who may do it; this checks that it may be done now.
 */
class Contracts
{
    public function __construct(private ContractRenderer $renderer, private SmsSender $sms) {}

    /**
     * Contracts of an applicant or member record, newest first.
     *
     * @return Collection<int, Contract>
     */
    public function of(Model $record): Collection
    {
        return Contract::withoutTenantScope()->of($record)->with('contractType')->latest('id')->get();
    }

    /**
     * The open (not cancelled) contract of a type for a record, if any.
     */
    public function openOf(Model $record, ContractType $type): ?Contract
    {
        return Contract::withoutTenantScope()->of($record)
            ->whereBelongsTo($type)
            ->where('status', '!=', ContractStatus::Cancelled)
            ->latest('id')
            ->first();
    }

    /**
     * Creates or changes a draft from the terms form's state: the
     * built-in fields and `custom.{key}` answers (ADR 0017).
     *
     * @param  array<string, mixed>  $state
     */
    public function saveDraft(?Contract $contract, ContractType $type, Model $record, array $state, ?User $actor): Contract
    {
        if ($contract !== null && ! $contract->isDraft()) {
            throw new ContractException('فقط پیش‌نویس قرارداد را می‌توان تغییر داد.');
        }

        $party = ContractParties::forRecord($record);

        if ($party === null || ! in_array($party->key(), $type->parties, true)) {
            throw new ContractException("قرارداد «{$type->title}» برای این شخص نیست.");
        }

        $form = ContractForm::for($type);
        $contract ??= new Contract([
            'contract_type_id' => $type->getKey(),
            'party' => $party->key(),
            'contractable_type' => $record->getMorphClass(),
            'contractable_id' => $record->getKey(),
            'status' => ContractStatus::Draft,
            'created_by_id' => $actor?->getKey(),
        ]);

        $contract->fill([
            'tenant_id' => $record->getAttribute('tenant_id'),
            'user_id' => $party->isApplicant($record) ? null : $party->user($record)?->getKey(),
            'starts_on' => self::builtIn($form, $state, 'starts_on'),
            'ends_on' => self::builtIn($form, $state, 'ends_on'),
            'amount' => ($amount = self::builtIn($form, $state, 'amount')) === null ? null : (int) $amount,
            'answers' => CustomFieldInputs::answers($form, (array) ($state[ResolvedForm::INPUT] ?? []), $contract->answers),
        ]);

        if ($contract->starts_on !== null && $contract->ends_on !== null && $contract->ends_on->lessThan($contract->starts_on)) {
            throw new ContractException('تاریخ پایان قرارداد پیش از تاریخ شروع آن است.');
        }

        $contract->save();

        return $contract;
    }

    /**
     * Fixes the text and gives the number; the person is told by SMS when
     * they may sign it in their panel.
     */
    public function issue(Contract $contract, ?User $actor): Contract
    {
        if (! $contract->isDraft()) {
            throw new ContractException('این قرارداد قبلاً صادر شده است.');
        }

        if ($contract->isForApplicant() || $contract->user_id === null) {
            throw new ContractException('قرارداد متقاضی پس از پذیرش او صادر می‌شود.');
        }

        DB::transaction(function () use ($contract, $actor): void {
            $type = ContractType::withoutTenantScope()->lockForUpdate()->findOrFail($contract->contract_type_id);
            $type->increment('last_number');

            $contract->forceFill([
                'number' => ($type->number_prefix ?? '').$type->last_number,
                'issued_at' => now(),
                'issued_by_id' => $actor?->getKey(),
            ]);
            $body = $this->renderer->body($contract);

            $contract->forceFill([
                'status' => ContractStatus::Issued,
                'body' => $body,
                'body_hash' => hash('sha256', $body),
            ])->save();

            $user = $contract->user;

            if ($contract->contractType->signature_method->allowsElectronic() && filled($user?->mobile)) {
                DB::afterCommit(fn () => $this->sms->send($user->mobile, "قرارداد «{$contract->contractType->title}» برای شما صادر شد. برای خواندن و امضای آن وارد ".config('app.name').' شوید.'));
            }
        });

        return $contract;
    }

    /**
     * The person signs in their panel, after confirming an SMS code sent to
     * their mobile (checked by the caller).
     */
    public function signElectronically(Contract $contract, User $user, ?string $ip): Contract
    {
        if ($contract->user_id !== $user->getKey() || ! $contract->canBeSignedElectronically()) {
            throw new ContractException('این قرارداد را نمی‌توانید امضا کنید.');
        }

        $contract->forceFill([
            'status' => ContractStatus::Signed,
            'signed_at' => now(),
            'signed_via' => SignedVia::Electronic,
            'signed_mobile' => $user->mobile,
            'signed_ip' => $ip,
        ])->save();

        return $contract;
    }

    /**
     * Staff record that the person signed the printed contract, with a
     * scan of the signed copy (an upload or a path on the local disk).
     */
    public function recordPaperSignature(Contract $contract, User $actor, UploadedFile|string $scan): Contract
    {
        if (! $contract->canBeSignedOnPaper()) {
            throw new ContractException('امضای کاغذی برای این قرارداد ثبت نمی‌شود.');
        }

        return DB::transaction(function () use ($contract, $actor, $scan): Contract {
            $adder = is_string($scan) ? $contract->addMediaFromDisk($scan, 'local') : $contract->addMedia($scan);
            $adder->toMediaCollection(Contract::SCAN_COLLECTION);

            $contract->forceFill([
                'status' => ContractStatus::Signed,
                'signed_at' => now(),
                'signed_via' => SignedVia::Paper,
                'signature_recorded_by_id' => $actor->getKey(),
            ])->save();

            return $contract;
        });
    }

    public function cancel(Contract $contract, User $actor, string $reason): Contract
    {
        if (! in_array($contract->status, [ContractStatus::Issued, ContractStatus::Signed], true)) {
            throw new ContractException('فقط قرارداد صادرشده را می‌توان لغو کرد؛ پیش‌نویس را حذف کنید.');
        }

        $contract->forceFill([
            'status' => ContractStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by_id' => $actor->getKey(),
            'cancellation_reason' => $reason,
        ])->save();

        return $contract;
    }

    /**
     * The applicant became a member: their open contracts move to the
     * member, its tenant and user, and drafts are issued.
     */
    public function accepted(Model $applicant, Model $member, ?User $actor): void
    {
        $party = ContractParties::forRecord($member);

        foreach ($this->of($applicant) as $contract) {
            if ($contract->status === ContractStatus::Cancelled) {
                continue;
            }

            $contract->forceFill([
                'tenant_id' => $member->getAttribute('tenant_id'),
                'contractable_type' => $member->getMorphClass(),
                'contractable_id' => $member->getKey(),
                'user_id' => $party?->user($member)?->getKey(),
            ])->save();

            if ($contract->isDraft()) {
                $this->issue($contract->unsetRelation('contractable')->unsetRelation('user')->unsetRelation('tenant'), $actor);
            }
        }
    }

    /**
     * The form's state for a contract: built-in fields and answers.
     *
     * @return array<string, mixed>
     */
    public static function state(?Contract $contract): array
    {
        return $contract === null ? [] : [
            'starts_on' => $contract->starts_on?->toDateString(),
            'ends_on' => $contract->ends_on?->toDateString(),
            'amount' => $contract->amount,
            ResolvedForm::INPUT => CustomFieldInputs::state($contract->answers),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function builtIn(ResolvedForm $form, array $state, string $key): mixed
    {
        return $form->asks($key) && filled($state[$key] ?? null) ? $state[$key] : null;
    }
}
