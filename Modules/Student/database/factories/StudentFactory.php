<?php

namespace Modules\Student\Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Student\Enums\StudentStatus;
use Modules\Student\Models\Student;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'grade_title' => 'متوسطه اول - هفتم',
            'status' => StudentStatus::Active,
            'enrolled_at' => now()->toDateString(),
        ];
    }
}
