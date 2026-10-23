<?php

namespace Modules\Site\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use App\PublicSite\PublicSite;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Modules\Site\Database\Factories\PageFactory;

/**
 * One public page of a tenant's site, at PublicSite::url($tenant, $path).
 * A page at "/" replaces the tenant's default home page (ADR 0010).
 */
#[Fillable(['tenant_id', 'title', 'path', 'is_published'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use BelongsToTenant, HasFactory;

    public const PATH_PATTERN = '/^\/$|^(\/[a-z0-9]+(?:-[a-z0-9]+)*)+$/';

    protected $table = 'site_pages';

    protected static function newFactory(): PageFactory
    {
        return PageFactory::new();
    }

    /**
     * "About-Us/" → "/about-us"; "" → "/".
     */
    public static function normalizePath(?string $path): string
    {
        $segments = array_filter(explode('/', Str::lower(trim((string) $path))), fn (string $segment): bool => $segment !== '');

        return '/'.implode('/', $segments);
    }

    /**
     * Why the path can't be used for a page of the tenant, or null if it can.
     * The first segment must not be an app route or a tenant slug, for every
     * tenant: any tenant may become primary and serve its pages from the root.
     */
    public static function pathProblem(string $path, Tenant $tenant, ?self $ignore = null): ?string
    {
        if (! preg_match(self::PATH_PATTERN, $path)) {
            return 'فقط حروف کوچک انگلیسی، عدد و خط تیره، با / بین بخش‌ها؛ مثلاً /about-us';
        }

        $firstSegment = Str::before(ltrim($path, '/'), '/');

        if ($firstSegment !== '' && (in_array($firstSegment, Tenant::routeSlugs(), true) || Tenant::query()->where('slug', $firstSegment)->exists())) {
            return "«/{$firstSegment}» برای بخش دیگری از سامانه یا یک ".config('sarasystem.tenant_label').' رزرو است.';
        }

        $taken = static::withoutTenantScope()
            ->where('tenant_id', $tenant->getKey())
            ->where('path', $path)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        return $taken ? 'این '.config('sarasystem.tenant_label').' صفحه‌ای با همین آدرس دارد.' : null;
    }

    public function url(): string
    {
        return PublicSite::url($this->tenant, $this->path);
    }

    protected function path(): Attribute
    {
        return Attribute::make(set: fn (?string $value): string => static::normalizePath($value));
    }

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }
}
