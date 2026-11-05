<x-filament-panels::page>
    <div
        x-data="{
            state: 'loading',
            scene: null,
            selected: null,
            autoRotate: false,
            names: true,
            time: @js($scene['time']),
            totals: @js($scene['totals']),
            siteCount: @js(count($scene['sites'])),
            stale: false,
            live: null,
            remount: null,
            async init() {
                try {
                    const { mountOrganizationScene, keepLive } = await import(@js(\Illuminate\Support\Facades\Vite::asset('Modules/Campus/resources/js/organization-scene.js')));

                    this.state = 'open';
                    await this.$nextTick();
                    this.remount = (data) => mountOrganizationScene(this.$refs.stage, this.$refs.tooltip, data, (site) => { this.selected = site; });
                    this.scene = this.remount(JSON.parse(this.$refs.stage.dataset.scene));
                    this.scene.setNames(true);
                    this.live = keepLive(@js($liveSeconds), () => this.$wire.liveData(), (data) => this.apply(data), () => { this.stale = true; });
                } catch (error) {
                    console.error(error);
                    this.state = 'failed';
                }
            },
            apply(data) {
                this.stale = false;
                this.time = data.time;
                this.totals = data.totals;
                this.siteCount = data.sites.length;

                if (! this.scene.update(data)) {
                    this.scene.dispose();
                    this.scene = this.remount(data);
                    this.scene.setNames(this.names);
                    this.scene.setAutoRotate(this.autoRotate);
                }

                if (this.selected) {
                    const site = data.sites.find((candidate) => candidate.id === this.selected.id);

                    site ? this.selected = site : this.closeCard();
                }
            },
            closeCard() {
                this.selected = null;
                this.scene?.clearSelection();
            },
            destroy() {
                this.live?.stop();
                this.scene?.dispose();
            },
        }"
        class="flex flex-col gap-3"
    >
        <p x-show="state === 'loading'" class="text-sm text-gray-500 dark:text-gray-400">در حال بارگذاری…</p>

        <div x-cloak x-show="state === 'open'" class="flex flex-col gap-2">
            <div class="relative">
                <div x-cloak x-show="selected" class="absolute start-3 top-3 z-20 w-80 max-w-[calc(100%-1.5rem)] rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-lg dark:border-white/10 dark:bg-gray-900">
                    <template x-if="selected">
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-semibold text-gray-950 dark:text-white" x-text="selected.name"></h3>
                                <x-filament::icon-button x-on:click="closeCard()" icon="heroicon-o-x-mark" color="gray" size="sm" label="بستن" />
                            </div>
                            <p class="text-gray-600 dark:text-gray-300">
                                <span x-text="selected.rooms.length + ' کلاس'"></span> ·
                                <span x-text="selected.people + ' نفر'"></span> ·
                                <span x-text="selected.live + ' در حال برگزاری'"></span>
                            </p>
                            <a x-bind:href="selected.url" class="font-medium text-primary-600 hover:underline dark:text-primary-400">ورود به ساختمان</a>
                        </div>
                    </template>
                </div>

                <div
                    x-ref="stage"
                    wire:ignore
                    data-scene="{{ json_encode($scene, JSON_UNESCAPED_UNICODE) }}"
                    class="relative h-[calc(100vh-20rem)] min-h-96 cursor-grab overflow-hidden rounded-xl border border-gray-200 bg-gradient-to-b from-sky-100 to-gray-100 active:cursor-grabbing dark:border-white/10 dark:from-gray-800 dark:to-gray-900"
                >
                    <div x-ref="tooltip" hidden class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-md bg-gray-950/90 px-2 py-1 text-xs text-white shadow"></div>
                </div>
            </div>

            @include('campus::filament.partials.scene-toolbar')

            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
                <span><span x-text="siteCount">{{ count($scene['sites']) }}</span> {{ config('sarasystem.tenant_label') }} · <span x-text="totals.rooms">{{ $scene['totals']['rooms'] }}</span> کلاس · <span x-text="totals.people">{{ $scene['totals']['people'] }}</span> نفر · <span x-text="totals.live">{{ $scene['totals']['live'] }}</span> در حال برگزاری</span>
                @foreach ($scene['states'] as $status)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm" style="background-color: {{ $status['color'] }}"></span>
                        {{ $status['label'] }}
                    </span>
                @endforeach
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-[#a855f7]"></span>
                    سقف بنفش: {{ config('sarasystem.tenant_label') }} اصلی
                </span>
                @include('campus::filament.partials.live-status')
                <span class="text-gray-400 dark:text-gray-500">چیدمان نمادین است و جای واقعی را نشان نمی‌دهد. پنجره‌های کرکره‌دار کلاسی ندارند. کلیک روی ساختمان: جزئیات</span>
            </div>
        </div>

        <p x-cloak x-show="state === 'failed'" class="text-sm text-warning-600 dark:text-warning-400">
            نمای سه‌بعدی در این مرورگر یا دستگاه باز نشد؛ فهرست زیر همان اطلاعات را دارد.
        </p>

        {{-- The same tenants for screen readers and where the 3D view can't open. --}}
        <ul x-cloak x-bind:class="state === 'failed' ? 'grid grid-cols-1 gap-1 text-sm sm:grid-cols-2' : 'sr-only'">
            @foreach ($scene['sites'] as $site)
                <li>
                    <a href="{{ $site['url'] }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $site['name'] }}</a>
                    · {{ count($site['rooms']) }} کلاس · {{ $site['people'] }} نفر · {{ $site['live'] }} در حال برگزاری
                </li>
            @endforeach
        </ul>
    </div>
</x-filament-panels::page>
