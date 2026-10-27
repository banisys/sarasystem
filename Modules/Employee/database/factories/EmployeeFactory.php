<?php

namespace Modules\Employee\Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Employee\Enums\EmploymentStatus;
use Modules\Employee\Enums\EmploymentType;
use Modules\Employee\Models\Employee;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'job_title' => fake()->jobTitle(),
            'employment_type' => EmploymentType::FullTime,
            'status' => EmploymentStatus::Active,
            'hired_at' => now()->toDateString(),
        ];
    }
}
