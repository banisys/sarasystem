<?php

namespace Modules\Class\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Class\Enums\OnlinePlatform;
use Modules\Class\Models\OnlineServer;

/**
 * @extends Factory<OnlineServer>
 */
class OnlineServerFactory extends Factory
{
    protected $model = OnlineServer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform' => OnlinePlatform::AdobeConnect,
            'title' => 'سرور '.fake()->unique()->numberBetween(1, 999),
            'host' => 'https://adobe.example.com',
            'username' => 'admin',
            'password' => 'secret',
            'settings' => ['folder_id' => '11003', 'login_prefix' => 'sara', 'verify_ssl' => true],
            'is_active' => true,
        ];
    }

    public function skyroom(): static
    {
        return $this->state(fn (): array => [
            'platform' => OnlinePlatform::Skyroom,
            'host' => 'https://www.skyroom.online/skyroom/api',
            'username' => null,
            'settings' => ['max_users' => 50, 'host_concurrent' => 3],
        ]);
    }
}
