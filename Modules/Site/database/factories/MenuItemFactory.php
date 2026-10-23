<?php

namespace Modules\Site\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Site\Models\Menu;
use Modules\Site\Models\MenuItem;
use Modules\Site\Models\Page;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_id' => Menu::factory(),
            'tenant_id' => fn (array $attributes): int => Menu::withoutTenantScope()->findOrFail($attributes['menu_id'])->tenant_id,
            'title' => fake()->word(),
            'link_type' => MenuItem::LINK_URL,
            'url' => 'https://example.com/'.fake()->slug(2),
            'sort' => 0,
        ];
    }

    public function linkingTo(Page $page): static
    {
        return $this->state(['link_type' => MenuItem::LINK_PAGE, 'page_id' => $page->id, 'url' => null]);
    }

    public function childOf(MenuItem $parent): static
    {
        return $this->state(['menu_id' => $parent->menu_id, 'tenant_id' => $parent->tenant_id, 'parent_id' => $parent->id]);
    }

    public function hidden(): static
    {
        return $this->state(['is_visible' => false]);
    }
}
