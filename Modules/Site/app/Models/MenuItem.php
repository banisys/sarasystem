<?php

namespace Modules\Site\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Site\Database\Factories\MenuItemFactory;

/**
 * One item of a menu's tree. It links to a page of the same site (follows
 * the page when its path changes), to an address as typed, or nowhere (a
 * title over a submenu) (ADR 0012).
 */
#[Fillable(['tenant_id', 'menu_id', 'parent_id', 'title', 'link_type', 'page_id', 'url', 'open_in_new_tab', 'is_visible', 'sort'])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use BelongsToTenant, HasFactory;

    public const LINK_PAGE = 'page';

    public const LINK_URL = 'url';

    public const LINK_NONE = 'none';

    /**
     * Addresses an item may link to: http(s), mailto, tel, a path on this
     * host or an anchor. Anything else (javascript: …) is never shown.
     */
    public const URL_PATTERN = '/^(https?:\/\/[^\s]+|mailto:[^\s]+|tel:[0-9+\-]+|\/[^\s]*|#[^\s]*)$/i';

    protected $table = 'site_menu_items';

    protected static function newFactory(): MenuItemFactory
    {
        return MenuItemFactory::new();
    }

    /**
     * @return array<string, string>
     */
    public static function linkTypes(): array
    {
        return [
            self::LINK_PAGE => 'صفحه‌ای از سایت',
            self::LINK_URL => 'آدرس دلخواه',
            self::LINK_NONE => 'بدون لینک (فقط عنوان زیرمنو)',
        ];
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return HasMany<MenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * Where the item leads on the site, or null: no link, a deleted or
     * unpublished page, or an address that is not allowed.
     */
    public function href(): ?string
    {
        return match ($this->link_type) {
            self::LINK_PAGE => $this->page?->is_published ? $this->page->url() : null,
            self::LINK_URL => preg_match(self::URL_PATTERN, (string) $this->url) ? $this->url : null,
            default => null,
        };
    }

    /**
     * Where the item leads, for the admin tree.
     */
    public function targetLabel(): string
    {
        return match ($this->link_type) {
            self::LINK_PAGE => $this->page ? "{$this->page->title} ({$this->page->path})" : 'صفحه حذف شده',
            self::LINK_URL => (string) $this->url,
            default => 'بدون لینک',
        };
    }

    /**
     * Ids of every item under this one.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $parentIds = [$this->id];

        while ($parentIds !== []) {
            $parentIds = static::query()->whereIn('parent_id', $parentIds)->pluck('id')->all();
            array_push($ids, ...$parentIds);
        }

        return $ids;
    }

    protected function casts(): array
    {
        return [
            'open_in_new_tab' => 'boolean',
            'is_visible' => 'boolean',
            'sort' => 'integer',
        ];
    }
}
