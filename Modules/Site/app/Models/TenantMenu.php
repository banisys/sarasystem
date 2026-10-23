<?php

namespace Modules\Site\Models;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which menu a tenant's site header shows (ADR 0012). Set by super admins
 * in the tenants list, across tenants: the menu may belong to another
 * tenant (usually the head office), so this is not a BelongsToTenant model.
 */
#[Fillable(['tenant_id', 'menu_id'])]
class TenantMenu extends Model
{
    protected $table = 'site_tenant_menus';

    public static function menuOf(Tenant $tenant): ?Menu
    {
        return static::query()->where('tenant_id', $tenant->getKey())->first()?->menu;
    }

    public static function assign(Tenant $tenant, ?int $menuId): void
    {
        if ($menuId === null) {
            static::query()->where('tenant_id', $tenant->getKey())->delete();

            return;
        }

        static::query()->updateOrCreate(['tenant_id' => $tenant->getKey()], ['menu_id' => $menuId]);
    }

    /**
     * @return BelongsTo<Menu, $this>
     */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class)->withoutGlobalScope(TenantScope::class);
    }
}
