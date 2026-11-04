<x-filament-panels::page>
    <div
        x-data="{
            state: 'loading',
            scene: null,
            selected: null,
            autoRotate: false,
            names: true,
            async init() {
                try {
                    const { mountOrganizationScene } = await import(@js(\Illuminate\Support\Facades\Vite::asset('Modules/Campus/resources/js/organization-scene.js')));

                    this.state = 'open';
                    await this.$nextTick();
                    this.scene = mountOrganizationScene(this.$refs.stage, this.$refs.tooltip, JSON.parse(this.$refs.stage.dataset.scene), (site) => { this.selected = site; });
                    this.scene.setNames(true);
                } catch (error) {
                    console.error(error);
                    this.state = 'failed';
                }
            },
            closeCard() {
                this.selected = null;
                this.scene?.clearSelection();
            },
            destroy() {
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
                <span>{{ count($sites) }} {{ config('sarasystem.tenant_label') }} · {{ $totals['rooms'] }} کلاس · {{ $totals['people'] }} نفر · {{ $totals['live'] }} در حال برگزاری</span>
                @foreach ($legend as $status)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm" style="background-color: {{ $status['color'] }}"></span>
                        {{ $status['label'] }}
                    </span>
                @endforeach
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-sm bg-[#a855f7]"></span>
                    سقف بنفش: {{ config('sarasystem.tenant_label') }} اصلی
                </span>
                <span class="text-gray-400 dark:text-gray-500">وضعیت ساعت {{ $time }}. چیدمان نمادین است و جای واقعی را نشان نمی‌دهد. کلیک روی ساختمان: جزئیات</span>
            </div>
        </div>

        <p x-cloak x-show="state === 'failed'" class="text-sm text-warning-600 dark:text-warning-400">
            نمای سه‌بعدی در این مرورگر یا دستگاه باز نشد؛ فهرست زیر همان اطلاعات را دارد.
        </p>

        {{-- The same tenants for screen readers and where the 3D view can't open. --}}
        <ul x-cloak x-bind:class="state === 'failed' ? 'grid grid-cols-1 gap-1 text-sm sm:grid-cols-2' : 'sr-only'">
            @foreach ($sites as $site)
                <li>
                    <a href="{{ $site['url'] }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $site['name'] }}</a>
                    · {{ count($site['rooms']) }} کلاس · {{ $site['people'] }} نفر · {{ $site['live'] }} در حال برگزاری
                </li>
            @endforeach
        </ul>
    </div>
</x-filament-panels::page>
