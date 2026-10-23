<x-filament-panels::page>
    @php
        $tenantLabel = config('sarasystem.tenant_label');
        $tenantPluralLabel = config('sarasystem.tenant_plural_label');
        $chip = 'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs ring-1 ring-inset';
        $membershipTitle = function (int $userId) use ($byUser, $tenants, $roles, $staff): string {
            return collect($byUser[$userId] ?? [])
                ->map(fn (array $roleIds, int $tenantId): string => ($tenants[$tenantId]->name ?? '?').': '.(
                    collect($roleIds)->map(fn (int $roleId): string => $roles[$roleId]->display_name)
                        ->when(isset($staff[$tenantId][$userId]), fn ($names) => $names->push('پنل'))
                        ->join('، ') ?: '—'
                ))
                ->join("\n");
        };
    @endphp

    {{-- Totals --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
        @foreach ([
            [$tenantPluralLabel.' فعال', $stats['tenants'], 'heroicon-o-building-office-2'],
            [$tenantPluralLabel.' غیرفعال', $stats['inactiveTenants'], 'heroicon-o-no-symbol'],
            ['کاربران عضو', $stats['users'], 'heroicon-o-users'],
            ['عضو چند '.$tenantLabel, $stats['multiTenantUsers'], 'heroicon-o-arrows-right-left'],
            ['نقش‌ها', $stats['roles'], 'heroicon-o-identification'],
        ] as [$label, $value, $icon])
            <div class="flex items-center gap-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <x-filament::icon :icon="$icon" class="h-6 w-6 text-primary-500" />
                <div>
                    <div class="text-xl font-semibold text-gray-950 dark:text-white">{{ number_format($value) }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- View switch and filters --}}
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <x-filament::tabs>
            <x-filament::tabs.item :active="$mode === 'tenants'" wire:click="$set('mode', 'tenants')" icon="heroicon-o-building-office-2">
                بر اساس {{ $tenantLabel }}
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$mode === 'matrix'" wire:click="$set('mode', 'matrix')" icon="heroicon-o-table-cells">
                ماتریس کاربر × {{ $tenantLabel }}
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$mode === 'roles'" wire:click="$set('mode', 'roles')" icon="heroicon-o-identification">
                بر اساس نقش
            </x-filament::tabs.item>
        </x-filament::tabs>

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:w-[42rem]">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="tenantId">
                    <option value="">همه‌ی {{ $tenantPluralLabel }}</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->id }}">{{ $tenant->name }}{{ $tenant->is_active ? '' : ' (غیرفعال)' }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="roleId">
                    <option value="">همه‌ی نقش‌ها</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->display_name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
            <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                <x-filament::input type="search" wire:model.live.debounce.400ms="search" placeholder="نام، موبایل یا کد ملی" />
            </x-filament::input.wrapper>
        </div>
    </div>

    <div wire:loading.class="opacity-50" class="transition-opacity">
        @if ($users->isEmpty())
            <x-filament::empty-state heading="کسی با این فیلترها پیدا نشد." icon="heroicon-o-user-group" />

        @elseif ($mode === 'tenants')
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 2xl:grid-cols-3">
                @foreach ($visibleTenants as $tenant)
                    @php
                        $groups = $byTenant[$tenant->id] ?? [];
                        $memberIds = collect($byUser)->filter(fn ($tenantRoles) => array_key_exists($tenant->id, $tenantRoles))->keys();
                        $staffOnly = $memberIds->filter(fn ($id) => ($byUser[$id][$tenant->id] ?? []) === []);
                    @endphp
                    @continue($memberIds->isEmpty() && ($roleId || filled($search)))

                    <x-filament::section :class="$tenant->is_active ? '' : 'opacity-60'">
                        <x-slot name="heading">
                            <span class="flex items-center gap-2">
                                {{ $tenant->name }}
                                @unless ($tenant->is_active)
                                    <x-filament::badge color="gray">غیرفعال</x-filament::badge>
                                @endunless
                            </span>
                        </x-slot>
                        <x-slot name="description">{{ number_format($memberIds->count()) }} عضو · {{ $tenant->slug }}</x-slot>

                        @php
                            $roleGroups = $roles
                                ->filter(fn ($role) => isset($groups[$role->id]))
                                ->map(fn ($role) => ['role' => $role, 'userIds' => collect($groups[$role->id])->unique()->sortBy(fn ($id) => $users[$id]->name ?? '')->values()]);
                        @endphp

                        @if ($memberIds->isEmpty())
                            <p class="text-sm text-gray-500 dark:text-gray-400">عضوی ندارد.</p>
                        @else
                            <div class="divide-y divide-gray-100 dark:divide-white/5">
                                @foreach ($roleGroups as $group)
                                    <div class="py-3 first:pt-0 last:pb-0">
                                        <div class="mb-2 flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $dots[$group['role']->id] }}"></span>
                                            <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $group['role']->display_name }}</span>
                                            <span class="ms-auto text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ number_format($group['userIds']->count()) }} نفر</span>
                                        </div>
                                        <ul class="grid grid-cols-1 gap-x-6 gap-y-1 ps-[1.125rem] sm:grid-cols-2">
                                            @foreach ($group['userIds']->take(\App\Filament\Pages\AccessMap::USERS_PER_GROUP) as $userId)
                                                @include('filament.pages.partials.access-map-user', ['userId' => $userId, 'tenant' => $tenant])
                                            @endforeach
                                        </ul>
                                        @if ($group['userIds']->count() > \App\Filament\Pages\AccessMap::USERS_PER_GROUP)
                                            <p class="mt-1 ps-[1.125rem] text-xs text-gray-400">و {{ number_format($group['userIds']->count() - \App\Filament\Pages\AccessMap::USERS_PER_GROUP) }} نفر دیگر…</p>
                                        @endif
                                    </div>
                                @endforeach

                                @if ($staffOnly->isNotEmpty())
                                    <div class="py-3 first:pt-0 last:pb-0">
                                        <div class="mb-2 flex items-center gap-2">
                                            <span class="h-2.5 w-2.5 shrink-0 rounded-full border border-gray-300 dark:border-gray-600"></span>
                                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">فقط ورود به پنل</span>
                                            <span class="ms-auto text-xs tabular-nums text-gray-400 dark:text-gray-500">{{ number_format($staffOnly->count()) }} نفر</span>
                                        </div>
                                        <ul class="grid grid-cols-1 gap-x-6 gap-y-1 ps-[1.125rem] sm:grid-cols-2">
                                            @foreach ($staffOnly as $userId)
                                                @include('filament.pages.partials.access-map-user', ['userId' => $userId, 'tenant' => $tenant])
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </x-filament::section>
                @endforeach
            </div>

        @elseif ($mode === 'matrix')
            @php $rows = $users->filter(fn ($user) => isset($byUser[$user->id])); @endphp
            <div class="overflow-x-auto rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-white/10">
                            <th class="sticky start-0 z-10 bg-white px-3 py-2 text-start font-medium text-gray-700 dark:bg-gray-900 dark:text-gray-200">کاربر</th>
                            @foreach ($visibleTenants as $tenant)
                                <th class="whitespace-nowrap px-3 py-2 text-center font-medium {{ $tenant->is_active ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400' }}">{{ $tenant->name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows->take(\App\Filament\Pages\AccessMap::MATRIX_ROWS) as $user)
                            <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50 dark:border-white/5 dark:hover:bg-white/5">
                                <td class="sticky start-0 z-10 whitespace-nowrap bg-white px-3 py-1.5 dark:bg-gray-900">
                                    <span class="flex items-center gap-1.5 text-gray-950 dark:text-white">
                                        {{ $user->name }}
                                        @if (count($byUser[$user->id]) > 1)
                                            <x-filament::icon icon="heroicon-m-arrows-right-left" class="h-4 w-4 text-amber-500" />
                                        @endif
                                    </span>
                                    <span class="text-xs text-gray-500" dir="ltr">{{ $user->mobile ?? $user->email }}</span>
                                </td>
                                @foreach ($visibleTenants as $tenant)
                                    <td class="px-3 py-1.5 text-center align-middle">
                                        @if (array_key_exists($tenant->id, $byUser[$user->id]))
                                            <div class="flex flex-wrap items-center justify-center gap-1">
                                                @foreach (array_unique($byUser[$user->id][$tenant->id]) as $roleIdInTenant)
                                                    <span class="{{ $chip }} {{ $colors[$roleIdInTenant] }}">{{ $roles[$roleIdInTenant]->display_name }}</span>
                                                @endforeach
                                                @if (isset($staff[$tenant->id][$user->id]))
                                                    <x-filament::icon icon="heroicon-m-computer-desktop" class="h-4 w-4 text-gray-400" />
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-300 dark:text-gray-700">·</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($rows->count() > \App\Filament\Pages\AccessMap::MATRIX_ROWS)
                <p class="text-sm text-gray-500">{{ number_format(\App\Filament\Pages\AccessMap::MATRIX_ROWS) }} نفر از {{ number_format($rows->count()) }} نفر نمایش داده شد؛ برای دیدن بقیه فیلتر کنید.</p>
            @endif

        @else
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @foreach ($roles as $role)
                    @php
                        $perTenant = $visibleTenants->mapWithKeys(fn ($tenant) => [$tenant->id => $role->id === $panelUserRoleId
                            ? count($staff[$tenant->id] ?? [])
                            : count(array_unique($byTenant[$tenant->id][$role->id] ?? []))]);
                        $max = max(1, $perTenant->max());
                    @endphp
                    @continue($roleId && (int) $roleId !== $role->id)

                    <x-filament::section>
                        <x-slot name="heading">
                            <span class="flex items-center gap-2">
                                <span class="{{ $chip }} {{ $role->id === $panelUserRoleId ? 'bg-gray-100 text-gray-700 ring-gray-300 dark:bg-white/10 dark:text-gray-200 dark:ring-white/20' : $colors[$role->id] }}">{{ $role->display_name }}</span>
                                <code class="text-xs text-gray-500" dir="ltr">{{ $role->name }}</code>
                            </span>
                        </x-slot>
                        <x-slot name="description">
                            {{ $role->tenant_id ? 'فقط در '.($tenants[$role->tenant_id]->name ?? '?') : 'سراسری' }}
                            · {{ number_format($perTenant->sum()) }} عضویت
                            @if ($role->isSystemRole()) · نقش سیستمی @endif
                        </x-slot>

                        <div class="space-y-1.5">
                            @foreach ($visibleTenants as $tenant)
                                @continue($perTenant[$tenant->id] === 0)
                                <div class="flex items-center gap-2 text-xs">
                                    <span class="w-28 shrink-0 truncate text-gray-600 dark:text-gray-300">{{ $tenant->name }}</span>
                                    <div class="h-2.5 flex-1 rounded-full bg-gray-100 dark:bg-white/10">
                                        <div class="h-2.5 rounded-full bg-primary-500" style="width: {{ round($perTenant[$tenant->id] / $max * 100) }}%"></div>
                                    </div>
                                    <span class="w-8 shrink-0 text-end text-gray-600 dark:text-gray-300">{{ number_format($perTenant[$tenant->id]) }}</span>
                                </div>
                            @endforeach
                            @if ($perTenant->sum() === 0)
                                <p class="text-sm text-gray-500 dark:text-gray-400">کسی این نقش را ندارد.</p>
                            @endif
                        </div>

                        <div class="mt-4">
                            <div class="mb-1 text-xs text-gray-500 dark:text-gray-400">دسترسی‌ها</div>
                            @if ($role->name === \App\Models\Role::SUPER_ADMIN)
                                <p class="text-sm text-gray-600 dark:text-gray-300">همه‌ی دسترسی‌ها، در همه‌ی {{ $tenantPluralLabel }}.</p>
                            @elseif ($role->permissions->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">بدون دسترسی</p>
                            @else
                                <div class="flex flex-wrap gap-1" dir="ltr">
                                    @foreach ($role->permissions->sortBy('name') as $permission)
                                        <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $permission->name }}</code>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
