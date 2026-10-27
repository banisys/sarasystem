<?php

namespace Modules\Student\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Student\Database\Factories\StudentFactory;
use Modules\Student\Enums\StudentStatus;

/**
 * A person studying in a tenant (ADR 0018). Identity (name, mobile,
 * national code) is the global user's; this row holds the enrollment in
 * one tenant. Created when a preregistration is approved at the last step
 * of its admission workflow.
 */
#[Fillable(['tenant_id', 'user_id', 'preregistration_id', 'grade_id', 'grade_title', 'student_code', 'status', 'enrolled_at', 'left_at'])]
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
     * Grades are the primary tenant's (ADR 0018).
     *
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return BelongsTo<Preregistration, $this>
     */
    public function preregistration(): BelongsTo
    {
        return $this->belongsTo(Preregistration::class)->withoutGlobalScope(TenantScope::class);
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
