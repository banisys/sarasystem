<?php

namespace Modules\Contract\Models;

use App\Contract\ContractParties;
use App\Contract\ContractParty;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Contract\Database\Factories\ContractFactory;
use Modules\Contract\Enums\ContractKind;
use Modules\Contract\Enums\ContractStatus;
use Modules\Contract\Enums\SignedVia;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One contract of one person (ADR 0020): prepared for an applicant record
 * (job application, preregistration) or a member record (employee,
 * learner), issued with its text fixed, then signed by the person in
 * their panel or on paper. An applicant's contracts move to the member
 * record, its tenant and user when the applicant is accepted. A signed
 * contract can get amendments and a renewal (ADR 0021).
 *
 * @property list<array<string, mixed>>|null $answers
 */
#[Fillable([
    'tenant_id', 'contract_type_id', 'parent_id', 'kind', 'party', 'contractable_type', 'contractable_id', 'user_id', 'number', 'status',
    'starts_on', 'ends_on', 'amount', 'answers', 'body', 'body_hash', 'created_by_id', 'issued_at', 'issued_by_id',
    'signed_at', 'signed_via', 'signed_mobile', 'signed_ip', 'signature_recorded_by_id',
    'cancelled_at', 'cancelled_by_id', 'cancellation_reason', 'expiry_reminded_at', 'signature_reminded_at', 'signature_reminder_count',
])]
class Contract extends Model implements HasMedia
{
    /** @use HasFactory<ContractFactory> */
    use BelongsToTenant, HasFactory, InteractsWithMedia;

    /** The scan of a contract signed on paper. */
    public const SCAN_COLLECTION = 'signed_scan';

    protected static function newFactory(): ContractFactory
    {
        return ContractFactory::new();
    }

    /**
     * Only the private disk: signed contracts are personal data.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::SCAN_COLLECTION)->useDisk('local')->singleFile();
    }

    /**
     * @param  Builder<Contract>  $query
     */
    public function scopeOf(Builder $query, Model $record): void
    {
        $query->where('contractable_type', $record->getMorphClass())->where('contractable_id', $record->getKey());
    }

    /**
     * What the person sees in their panel: issued, signed or cancelled.
     *
     * @param  Builder<Contract>  $query
     */
    public function scopeVisibleToPerson(Builder $query, User $user): void
    {
        $query->whereBelongsTo($user)->where('status', '!=', ContractStatus::Draft);
    }

    public function isDraft(): bool
    {
        return $this->status === ContractStatus::Draft;
    }

    public function isForApplicant(): bool
    {
        $record = $this->contractable;

        return $record !== null && ($this->partyDescriptor()?->isApplicant($record) ?? false);
    }

    public function canBeSignedElectronically(): bool
    {
        return $this->status === ContractStatus::Issued && $this->contractType->signature_method->allowsElectronic();
    }

    public function canBeSignedOnPaper(): bool
    {
        return $this->status === ContractStatus::Issued && $this->contractType->signature_method->allowsPaper();
    }

    /**
     * A signed contract of its own (not an amendment) can be amended.
     */
    public function canBeAmended(): bool
    {
        return $this->status === ContractStatus::Signed && $this->kind !== ContractKind::Amendment;
    }

    /**
     * A signed contract (not an amendment) can be renewed once; a
     * cancelled renewal can be made again.
     */
    public function canBeRenewed(): bool
    {
        return $this->status === ContractStatus::Signed
            && $this->kind !== ContractKind::Amendment
            && ($this->relationLoaded('renewals') ? $this->renewals : $this->renewals()->get())
                ->every(fn (Contract $renewal): bool => $renewal->status === ContractStatus::Cancelled);
    }

    public function partyDescriptor(): ?ContractParty
    {
        return ContractParties::find($this->party);
    }

    public function personName(): string
    {
        $record = $this->contractable;

        return $record === null ? '—' : ($this->partyDescriptor()?->personName($record) ?? '—');
    }

    /**
     * Types are the primary tenant's.
     *
     * @return BelongsTo<ContractType, $this>
     */
    public function contractType(): BelongsTo
    {
        return $this->belongsTo(ContractType::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * Unscoped: an applicant record is the primary tenant's while its case
     * may be open in another tenant's panel.
     *
     * @return MorphTo<Model, $this>
     */
    public function contractable(): MorphTo
    {
        return $this->morphTo()->withoutGlobalScope(TenantScope::class);
    }

    /**
     * An amendment's main contract, or the contract a renewal follows.
     * Unscoped: both are the same person's, in their tenant.
     *
     * @return BelongsTo<Contract, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function amendments(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->withoutGlobalScope(TenantScope::class)->where('kind', ContractKind::Amendment);
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function renewals(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->withoutGlobalScope(TenantScope::class)->where('kind', ContractKind::Renewal);
    }

    /**
     * The person who signs; set once the contract belongs to a member.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signatureRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signature_recorded_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    protected function casts(): array
    {
        return [
            'status' => ContractStatus::class,
            'kind' => ContractKind::class,
            'signed_via' => SignedVia::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'amount' => 'integer',
            'answers' => 'array',
            'issued_at' => 'datetime',
            'signed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'expiry_reminded_at' => 'datetime',
            'signature_reminded_at' => 'datetime',
        ];
    }
}
