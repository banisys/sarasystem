<?php

namespace Modules\Student\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\WorkflowCase;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Modules\Student\Database\Factories\PreregistrationFactory;
use Modules\Student\Enums\Gender;
use Modules\Student\Enums\Nationality;
use Modules\Student\Enums\PreregistrationStatus;

/**
 * A student preregistration sent through the public «پیش‌ثبت‌نام» form
 * (ADR 0018). No user is created until the student is admitted. A national
 * code has at most one open preregistration (closed_at null) in the whole
 * installation.
 *
 * @property list<array{key: string, label: string, type: string, section: string|null, value: mixed}>|null $custom_fields answers to the fields the organization added (ADR 0017)
 */
#[Fillable([
    'tenant_id', 'grade_id', 'grade_title', 'preferred_tenant_id', 'status',
    'first_name', 'last_name', 'father_name', 'nationality', 'national_code', 'birth_certificate_number', 'birth_date', 'birth_place', 'gender',
    'mobile', 'phone', 'email', 'postal_code', 'city', 'address', 'previous_school', 'last_average', 'custom_fields',
])]
class Preregistration extends Model
{
    /** @use HasFactory<PreregistrationFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'student_preregistrations';

    protected static function newFactory(): PreregistrationFactory
    {
        return PreregistrationFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (Preregistration $preregistration): void {
            if (! $preregistration->status->isClosed()) {
                $preregistration->closed_at = null;
            } elseif ($preregistration->closed_at === null) {
                $preregistration->closed_at = now();
            }
        });
    }

    /**
     * The open preregistration of a national code, in any tenant.
     */
    public static function openFor(string $nationalCode): ?self
    {
        return static::withoutTenantScope()->open()->where('national_code', $nationalCode)->first();
    }

    /**
     * @param  Builder<Preregistration>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('closed_at');
    }

    /**
     * Grades are the primary tenant's, like the preregistration.
     *
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * The school (tenant) the family would like, if asked.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function preferredTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'preferred_tenant_id');
    }

    /**
     * The admission workflow's case (ADR 0016); the latest one if it was
     * started again.
     *
     * @return MorphOne<WorkflowCase, $this>
     */
    public function workflowCase(): MorphOne
    {
        return $this->morphOne(WorkflowCase::class, 'subject')->withoutGlobalScope(TenantScope::class)->latestOfMany();
    }

    /**
     * Whether sending the form again may still change it: not once a
     * reviewer has it, i.e. after the first step was approved or while
     * someone has claimed it (ADR 0016).
     */
    public function canBeUpdatedByApplicant(): bool
    {
        $case = $this->workflowCase;

        if ($case === null || ! $case->isInProgress()) {
            return $this->status === PreregistrationStatus::Submitted;
        }

        return $case->current_step === 0 && $case->claimed_by_id === null;
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    protected function casts(): array
    {
        return [
            'status' => PreregistrationStatus::class,
            'closed_at' => 'datetime',
            'nationality' => Nationality::class,
            'gender' => Gender::class,
            'birth_date' => 'date',
            'last_average' => 'decimal:2',
            'custom_fields' => 'array',
        ];
    }
}
