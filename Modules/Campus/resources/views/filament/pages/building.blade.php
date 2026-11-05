<x-filament-panels::page>
    @if ($scene['rooms'] === [])
        <p class="text-sm text-gray-500 dark:text-gray-400">هنوز کلاس فعالی در این واحد نیست؛ با ساختن کلاس، اتاق آن در ساختمان دیده می‌شود.</p>
    @else
        <div
            x-data="{
                state: 'loading',
                scene: null,
                selected: null,
                autoRotate: false,
                names: false,
                time: @js($scene['time']),
                legend: @js($scene['legend']),
                stale: false,
                live: null,
                remount: null,
                async init() {
                    try {
                        const { mountBuildingScene, keepLive } = await import(@js(\Illuminate\Support\Facades\Vite::asset('Modules/Campus/resources/js/building-scene.js')));

                        this.state = 'open';
                        await this.$nextTick();
                        this.remount = (data) => mountBuildingScene(this.$refs.stage, this.$refs.tooltip, data, (room) => { this.selected = room; });
                        this.scene = this.remount(JSON.parse(this.$refs.stage.dataset.scene));
                        this.live = keepLive(@js($liveSeconds), () => this.$wire.liveData(), (data) => this.apply(data), () => { this.stale = true; });
                    } catch (error) {
                        console.error(error);
                        this.state = 'failed';
                    }
                },
                apply(data) {
                    this.stale = false;
                    this.time = data.time;
                    this.legend = data.legend;

                    if (data.rooms.length === 0) {
                        location.reload();

                        return;
                    }

                    if (! this.scene.update(data)) {
                        this.scene.dispose();
                        this.scene = this.remount(data);
                        this.scene.setNames(this.names);
                        this.scene.setAutoRotate(this.autoRotate);
                    }

                    if (this.selected) {
                        const room = data.rooms.find((candidate) => candidate.id === this.selected.id);

                        room ? this.selected = room : this.closeCard();
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
                {{-- The picked room. --}}
                <div x-cloak x-show="selected" class="absolute start-3 top-3 z-20 w-80 max-w-[calc(100%-1.5rem)] rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-lg dark:border-white/10 dark:bg-gray-900">
                    <template x-if="selected">
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="font-semibold text-gray-950 dark:text-white" x-text="selected.title"></h3>
                                <x-filament::icon-button x-on:click="closeCard()" icon="heroicon-o-x-mark" color="gray" size="sm" label="بستن" />
                            </div>
                            <p class="text-gray-600 dark:text-gray-300">
                                <span x-text="selected.mode"></span> ·
                                <span x-text="selected.capacity ? selected.people + ' از ' + selected.capacity + ' نفر' : selected.people + ' نفر'"></span>
                            </p>
                            <template x-if="selected.live">
                                <p class="text-gray-950 dark:text-white">
                                    هم‌اکنون: <span x-text="[selected.live.subject, selected.live.teacher, selected.live.time].filter(Boolean).join(' · ')"></span>
                                </p>
                            </template>
                            <template x-if="! selected.live && selected.next">
                                <p class="text-gray-600 dark:text-gray-300">جلسه‌ی بعدی امروز: <span x-text="selected.next"></span></p>
                            </template>
                            <div class="flex flex-wrap gap-2">
                                <template x-if="selected.live?.url">
                                    <a x-bind:href="selected.live.url" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline dark:text-primary-400">صفحه‌ی جلسه</a>
                                </template>
                                <template x-if="selected.url">
                                    <a x-bind:href="selected.url" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline dark:text-primary-400">صفحه‌ی کلاس</a>
                                </template>
                            </div>
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
                    @foreach ($scene['legend'] as $key => $status)
                        <span class="inline-flex items-center gap-1.5">
                            <span class="size-2.5 rounded-sm" style="background-color: {{ $status['color'] }}"></span>
                            {{ $status['label'] }} <span x-text="legend[@js($key)].count">{{ $status['count'] }}</span>
                        </span>
                    @endforeach
                    @include('campus::filament.partials.live-status')
                    <span class="text-gray-400 dark:text-gray-500">ساختمان نمادین است: هر کلاس یک اتاق است و کلاس آنلاین صفحه‌نمایش دارد. رنگ اتاق: وضعیت کلاس. جاهای کرکره‌دار کلاسی ندارند. کلیک روی اتاق: جزئیات کلاس</span>
                </div>
            </div>

            <p x-cloak x-show="state === 'failed'" class="text-sm text-warning-600 dark:text-warning-400">
                نمای سه‌بعدی در این مرورگر یا دستگاه باز نشد؛ فهرست زیر همان اطلاعات را دارد.
            </p>

            {{-- The same rooms for screen readers and where the 3D view can't open. --}}
            <ul x-cloak x-bind:class="state === 'failed' ? 'grid grid-cols-1 gap-1 text-sm sm:grid-cols-2' : 'sr-only'">
                @foreach ($scene['rooms'] as $room)
                    <li>
                        @if ($room['url'])
                            <a href="{{ $room['url'] }}" class="text-primary-600 hover:underline dark:text-primary-400">{{ $room['title'] }}</a>
                        @else
                            {{ $room['title'] }}
                        @endif
                        · {{ $scene['legend'][$room['state']]['label'] }}{{ $room['next'] ? ' · '.$room['next'] : '' }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</x-filament-panels::page>
