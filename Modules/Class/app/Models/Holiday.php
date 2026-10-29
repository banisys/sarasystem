<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Class\Database\Factories\HolidayFactory;
use Modules\Class\Services\SessionPlanner;

/**
 * Days without classes, one day or a range (ADR 0022). The primary
 * tenant's holidays are the whole organization's; another tenant's are its
 * own only. Schedules skip them when building sessions (ADR 0023).
 */
#[Fillable(['tenant_id', 'title', 'starts_on', 'ends_on'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'class_holidays';

    /**
     * Sessions on a new holiday go, and come back when it goes (ADR 0023).
     */
    protected static function booted(): void
    {
        static::saved(fn (Holiday $holiday) => app(SessionPlanner::class)->holidayChanged($holiday));
        static::deleted(fn (Holiday $holiday) => app(SessionPlanner::class)->holidayChanged($holiday));
    }

    protected static function newFactory(): HolidayFactory
    {
        return HolidayFactory::new();
    }

    /**
     * The holidays that apply in the tenant: its own and the organization's.
     *
     * @return Builder<Holiday>
     */
    public static function applyingIn(Tenant $tenant): Builder
    {
        return static::withoutTenantScope()
            ->whereIn('tenant_id', array_unique(array_filter([$tenant->getKey(), Tenant::primary()?->getKey()])));
    }

    /**
     * @param  Builder<Holiday>  $query
     */
    public function scopeCovering(Builder $query, CarbonInterface $date): void
    {
        $query->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date);
    }

    public function isOrganizationWide(): bool
    {
        return $this->tenant_id === Tenant::primary()?->getKey();
    }

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
