<?php

namespace Modules\Site\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Site\Database\Factories\MenuFactory;

/**
 * A named menu, made in one tenant's panel; its items form a tree. Super
 * admins choose in the tenants list which menu each tenant's site header
 * shows, so one menu (e.g. the head office's) may serve several tenants
 * (ADR 0012).
 */
#[Fillable(['tenant_id', 'title'])]
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'site_menus';

    protected static function newFactory(): MenuFactory
    {
        return MenuFactory::new();
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * The top-level items in order, each with its `children` relation set
     * down the whole tree; loaded with one query. Not limited to the current
     * tenant: another tenant's site may show this menu, and its items and
     * pages belong to the menu's tenant.
     *
     * @return Collection<int, MenuItem>
     */
    public function itemTree(): Collection
    {
        $byParent = $this->items()->withoutGlobalScope(TenantScope::class)
            ->with(['page' => fn ($query) => $query->withoutGlobalScope(TenantScope::class)])
            ->orderBy('sort')->orderBy('id')->get()
            ->groupBy(fn (MenuItem $item): int => $item->parent_id ?? 0);

        $attachChildren = function (MenuItem $item) use (&$attachChildren, $byParent): void {
            $item->setRelation('children', new Collection($byParent->get($item->id, [])));
            $item->children->each($attachChildren);
        };

        return (new Collection($byParent->get(0, [])))->each($attachChildren);
    }

    /**
     * What the site shows: visible items that lead somewhere (or hold a
     * submenu), as plain arrays. A hidden item hides its submenu too.
     *
     * @return list<array{title: string, url: string|null, new_tab: bool, children: list<mixed>}>
     */
    public function publicItems(): array
    {
        $toArray = function (Collection $items) use (&$toArray): array {
            return $items
                ->filter(fn (MenuItem $item): bool => $item->is_visible && ($item->link_type === MenuItem::LINK_NONE || $item->href() !== null))
                ->map(fn (MenuItem $item): array => [
                    'title' => $item->title,
                    'url' => $item->href(),
                    'new_tab' => $item->open_in_new_tab,
                    'children' => $toArray($item->children),
                ])
                ->values()
                ->all();
        };

        return $toArray($this->itemTree());
    }
}
