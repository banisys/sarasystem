<?php

namespace Modules\Class\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Class\Models\ClassRole;

/**
 * @extends Factory<ClassRole>
 */
class ClassRoleFactory extends Factory
{
    protected $model = ClassRole::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => 'نقش '.fake()->unique()->numberBetween(1, 9999),
            'is_attendee' => false,
            'takes_attendance' => false,
            'records_content' => false,
            'allowed_role_ids' => [],
        ];
    }

    public function attendee(): static
    {
        return $this->state(['title' => 'فراگیر', 'is_attendee' => true]);
    }

    public function teacher(): static
    {
        return $this->state(['title' => 'مدرس', 'takes_attendance' => true, 'records_content' => true]);
    }
}
