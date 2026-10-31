<?php

namespace Modules\Employee\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Employee\Database\Factories\EmployeeFactory;
use Modules\Employee\Enums\EmploymentStatus;
use Modules\Employee\Enums\EmploymentType;

/**
 * A person employed in a tenant (ADR 0016). Identity (name, mobile,
 * national code) is the global user's; this row holds the employment in
 * one tenant. Created by RegisterEmployee: at the last step of a hiring
 * workflow, or by quick entry and import (ADR 0038).
 */
#[Fillable(['tenant_id', 'user_id', 'job_application_id', 'job_title', 'personnel_code', 'employment_type', 'status', 'hired_at', 'left_at'])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function jobApplication(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class);
    }

    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'status' => EmploymentStatus::class,
            'hired_at' => 'date',
            'left_at' => 'date',
        ];
    }
}
