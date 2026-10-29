<?php

namespace Modules\Student\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Guardianship;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Student\Database\Factories\StudentFactory;
use Modules\Student\Enums\StudentStatus;

/**
 * A learner of a tenant (ADR 0018): a pupil, a university student, an
 * institute's trainee or a company's employee in training; the person who
 * is put into classes. Identity (name, mobile, national code) is the global
 * user's; this row holds the membership as a learner in one tenant. Created
 * when a preregistration is approved at the last step of its admission
 * workflow.
 */
#[Fillable(['tenant_id', 'user_id', 'preregistration_id', 'student_code', 'status', 'enrolled_at', 'left_at'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): StudentFactory
    {
        return StudentFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Preregistration, $this>
     */
    public function preregistration(): BelongsTo
    {
        return $this->belongsTo(Preregistration::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * The learner's parents and guardians (ADR 0027), whatever the tenant.
     *
     * @return HasMany<Guardianship, $this>
     */
    public function guardianships(): HasMany
    {
        return $this->hasMany(Guardianship::class, 'ward_id', 'user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => StudentStatus::class,
            'enrolled_at' => 'date',
            'left_at' => 'date',
        ];
    }
}
