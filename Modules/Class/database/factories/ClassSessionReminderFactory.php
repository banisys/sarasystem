<?php

namespace Modules\Class\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\ClassSessionReminder;

/** @extends Factory<ClassSessionReminder> */
class ClassSessionReminderFactory extends Factory
{
    protected $model = ClassSessionReminder::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'session_id' => ClassSession::factory(),
            'tenant_id' => fn (array $attributes): int => ClassSession::withoutTenantScope()->findOrFail($attributes['session_id'])->tenant_id,
            'author_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
