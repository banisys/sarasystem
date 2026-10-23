<?php

namespace Modules\Site\Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Site\Models\Page;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'title' => fake()->sentence(3),
            'path' => '/page-'.Str::lower(Str::random(8)),
            'is_published' => true,
        ];
    }

    public function draft(): static
    {
        return $this->state(['is_published' => false]);
    }
}
