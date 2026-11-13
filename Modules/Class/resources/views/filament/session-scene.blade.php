{{-- The session's 3D view, full page with every control (ADR 0049). Its script loads only when the button is pressed. --}}
@if ($isExam || $scene['seats'] !== [])
    @php
        $toggleOn = 'ring-2 ring-primary-500 bg-primary-50! text-primary-700! dark:bg-primary-500/20! dark:text-primary-200!';
        $seatPositions = $scene['layout'] === null ? null : array_flip(array_filter($scene['layout']['seats'], fn ($index) => $index !== null));
    @endphp
    <div
        wire:key="session-scene-{{ md5(json_encode($scene)) }}"
        x-data="{
            state: 'closed',
            scene: null,
            autoRotate: false,
            names: false,
            walls: true,
            async show() {
                this.state = 'loading';
                this.autoRotate = false;
                this.names = false;
                this.walls = true;

                try {
                    const { mountSessionScene } = await import(@js(\Illuminate\Support\Facades\Vite::asset('Modules/Class/resources/js/session-scene.js')));

                    this.state = 'open';
                    await this.$nextTick();
                    this.scene = mountSessionScene(this.$refs.stage, this.$refs.tooltip, JSON.parse(this.$refs.stage.dataset.scene));
                } catch (error) {
                    console.error(error);
                    this.state = 'failed';
                }
            },
            hide() {
                this.scene?.dispose();
                this.scene = null;
                this.state = 'closed';
                this.$nextTick(() => this.$refs.trigger?.focus());
            },
            destroy() {
                this.scene?.dispose();
            },
        }"
        @if ($isExam)
            x-bind:class="state === 'failed' && 'basis-full'"
        @endif
        class="flex flex-col items-start gap-3"
    >
        <div class="flex w-full flex-wrap items-center justify-between gap-2">
            @unless ($isExam)
                <span class="text-sm font-medium text-gray-950 dark:text-white">نمای کلاس</span>
            @endunless

            <x-filament::button
                x-ref="trigger"
                x-on:click="show()"
                x-bind:disabled="state === 'loading'"
                :color="$isExam ? \Filament\Support\Colors\Color::Violet : 'gray'"
                :size="$isExam ? 'md' : 'sm'"
                :outlined="$isExam"
                icon="heroicon-m-cube"
            >
                <span x-text="state === 'loading' ? 'در حال بارگذاری…' : 'نمای سه‌بعدی'">نمای سه‌بعدی</span>
            </x-filament::button>
        </div>

        {{-- On the body: a parent with a backdrop filter (the dark theme) would cut off a fixed element. --}}
        <template x-teleport="body">
            <div
                x-cloak
                x-show="state === 'open'"
                x-trap.noscroll="state === 'open'"
                @if ($isExam)
                    x-on:keydown.escape.stop="hide()"
                @endif
                role="dialog"
                aria-modal="true"
                aria-label="نمای سه‌بعدی {{ $title }}"
                dir="rtl"
                class="fixed inset-0 z-[100] flex flex-col bg-gray-100 dark:bg-gray-950"
            >
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 bg-white px-4 py-2 dark:border-white/10 dark:bg-gray-900">
                    <h2 class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $title }}</h2>
                    <x-filament::icon-button x-on:click="hide()" icon="heroicon-o-x-mark" color="gray" label="بستن نمای سه‌بعدی" />
                </div>

                <div
                    x-ref="stage"
                    data-scene="{{ json_encode($scene, JSON_UNESCAPED_UNICODE) }}"
                    @class([
                        'relative min-h-0 flex-1 cursor-grab overflow-hidden active:cursor-grabbing',
                        'bg-slate-950' => $scene['virtual'],
                        'bg-gradient-to-b from-sky-50 to-gray-100 dark:from-gray-800 dark:to-gray-900' => ! $scene['virtual'],
                    ])
                >
                    <div x-ref="tooltip" hidden class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-md bg-gray-950/90 px-2 py-1 text-xs text-white shadow"></div>
                </div>

                <div class="flex flex-col gap-2 border-t border-gray-200 bg-white px-4 py-2 dark:border-white/10 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <div class="flex flex-wrap items-center gap-1" role="group" aria-label="نماهای آماده">
                            <x-filament::button x-on:click="scene.view('corner')" color="gray" size="xs">سه‌رخ</x-filament::button>
                            <x-filament::button x-on:click="scene.view('top')" color="gray" size="xs">از بالا</x-filament::button>
                            <x-filament::button x-on:click="scene.view('front')" color="gray" size="xs">از جلو</x-filament::button>
                            <x-filament::button x-on:click="scene.view('back')" color="gray" size="xs">از عقب</x-filament::button>
                        </div>
                        <div class="flex items-center gap-1" role="group" aria-label="زوم">
                            <x-filament::icon-button x-on:click="scene.zoom(0.75)" icon="heroicon-m-magnifying-glass-plus" color="gray" label="نزدیک‌تر" />
                            <x-filament::icon-button x-on:click="scene.zoom(1.33)" icon="heroicon-m-magnifying-glass-minus" color="gray" label="دورتر" />
                        </div>
                        <div class="flex flex-wrap items-center gap-1" role="group" aria-label="گزینه‌ها">
                            <x-filament::button x-on:click="autoRotate = ! autoRotate; scene.setAutoRotate(autoRotate)" x-bind:aria-pressed="autoRotate" x-bind:class="autoRotate && '{{ $toggleOn }}'" color="gray" size="xs" icon="heroicon-m-arrow-path">چرخش خودکار</x-filament::button>
                            <x-filament::button x-on:click="names = ! names; scene.setNames(names)" x-bind:aria-pressed="names" x-bind:class="names && '{{ $toggleOn }}'" color="gray" size="xs" icon="heroicon-m-tag">نام همه</x-filament::button>
                            <x-filament::button x-on:click="walls = ! walls; scene.setWalls(walls)" x-bind:aria-pressed="walls" x-bind:class="walls && '{{ $toggleOn }}'" color="gray" size="xs" icon="heroicon-m-home-modern">دیوارها</x-filament::button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
                        @foreach ($scene['statuses'] as $status)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="size-2.5 rounded-full" style="background-color: {{ $status['color'] }}"></span>
                                {{ $status['label'] }} {{ $status['count'] }}
                            </span>
                        @endforeach
                        @foreach ($scene['roles'] as $role)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="size-2.5 rounded-sm" style="background-color: {{ $role['color'] }}"></span>
                                {{ $role['label'] }} {{ $role['count'] }}
                            </span>
                        @endforeach
                        <span class="text-gray-400 dark:text-gray-500">
                            @if ($seatPositions === null)
                                جای نفرها نمادین است.
                            @else
                                چیدمان ذخیره‌شده · بدون صندلی: {{ count($scene['seats']) - count($seatPositions) }}
                            @endif
                            کشیدن: چرخش · چرخ موس یا دو انگشت: زوم · کلیک راست یا کلیدهای جهت: جابه‌جایی · کلیک روی صندلی: نام
                        </span>
                    </div>
                </div>
            </div>
        </template>

        <p x-cloak x-show="state === 'failed'" role="status" class="text-sm text-warning-600 dark:text-warning-400">
            نمای سه‌بعدی در این مرورگر یا دستگاه باز نشد؛ فهرست زیر همان اطلاعات را دارد.
        </p>

        {{-- The same people for screen readers and where the 3D view can't open. --}}
        <ul x-cloak x-bind:class="state === 'failed' ? 'grid grid-cols-1 gap-1 text-sm sm:grid-cols-2' : 'sr-only'">
            @foreach ($scene['seats'] as $index => $seat)
                <li class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $scene['statuses'][$seat['status']]['color'] }}"></span>
                    {{ $seat['name'] }} · {{ $seat['label'] }}
                    @if ($seatPositions !== null)
                        · {{ isset($seatPositions[$index]) ? 'صندلی '.($seatPositions[$index] + 1) : 'بدون صندلی' }}
                    @endif
                </li>
            @endforeach
            @foreach ([...$scene['teachers'], ...$scene['staff']] as $person)
                <li class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 shrink-0 rounded-sm" style="background-color: {{ $person['color'] }}"></span>
                    {{ $person['name'] }} · {{ $person['role'] }}
                </li>
            @endforeach
        </ul>
    </div>
@endif
