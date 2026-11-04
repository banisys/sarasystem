<?php

namespace Modules\Campus\Services;

use App\Campus\BuildingRooms;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Collection;
use Modules\Campus\Filament\Pages\Building;

/**
 * The tenants a user sees on the organization map (ADR 0052): those of their
 * panel where they may see the rooms. Permissions are per tenant and a user
 * remembers their roles, so each tenant is asked with a fresh copy of the user.
 */
class OrganizationSites
{
    public function canSeeAny(User $user): bool
    {
        return once(fn (): bool => $this->tenants($user)->contains(fn (Tenant $tenant): bool => $tenant->execute(
            fn (): bool => BuildingRooms::canView($user->withoutRelations()),
        )));
    }

    /**
     * The primary tenant first, then by name.
     *
     * @return list<array{id: int, name: string, primary: bool, url: string, rooms: list<array{state: string}>, people: int, live: int}>
     */
    public function for(User $user): array
    {
        return $this->tenants($user)
            ->sortBy(fn (Tenant $tenant): string => ($tenant->is_primary ? '0' : '1').$tenant->name)
            ->map(fn (Tenant $tenant): ?array => $tenant->execute(function () use ($tenant, $user): ?array {
                $scoped = $user->withoutRelations();

                if (! BuildingRooms::canView($scoped)) {
                    return null;
                }

                $rooms = collect(BuildingRooms::for($scoped));

                return [
                    'id' => $tenant->getKey(),
                    'name' => $tenant->name,
                    'primary' => (bool) $tenant->is_primary,
                    'url' => Building::getUrl(tenant: $tenant),
                    // Only what the map draws and counts: no class names or links of other tenants.
                    'rooms' => $rooms->map(fn (array $room): array => ['state' => $room['state']])->all(),
                    'people' => $rooms->sum('people'),
                    'live' => $rooms->where('state', BuildingRooms::LIVE)->count(),
                ];
            }))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants(User $user): Collection
    {
        return collect($user->getTenants(Filament::getPanel('admin')));
    }
}
