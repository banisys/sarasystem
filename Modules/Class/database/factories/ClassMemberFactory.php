<?php

namespace Modules\Class\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassRole;
use Modules\Class\Models\Classroom;

/**
 * @extends Factory<ClassMember>
 */
class ClassMemberFactory extends Factory
{
    protected $model = ClassMember::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'tenant_id' => fn (array $attributes): int => Classroom::withoutTenantScope()->findOrFail($attributes['classroom_id'])->tenant_id,
            'user_id' => User::factory(),
            'class_role_id' => ClassRole::factory(),
            'joined_on' => today()->toDateString(),
        ];
    }
}
