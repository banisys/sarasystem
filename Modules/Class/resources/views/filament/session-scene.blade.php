{{-- The session's 3D view (ADR 0049). Its script loads only when the button is pressed. --}}
@if ($scene['seats'] !== [])
    <div
        wire:key="session-scene-{{ md5(json_encode($scene)) }}"
        x-data="{
            state: 'closed',
            scene: null,
            async show() {
                this.state = 'loading';

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
            },
            destroy() {
                this.scene?.dispose();
            },
        }"
        class="flex flex-col gap-3"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-sm font-medium text-gray-950 dark:text-white">نمای کلاس</span>

            <x-filament::button x-show="state === 'closed' || state === 'loading'" x-on:click="show()" x-bind:disabled="state === 'loading'" color="gray" size="sm" icon="heroicon-m-cube">
                <span x-text="state === 'loading' ? 'در حال بارگذاری…' : 'نمای سه‌بعدی'"></span>
            </x-filament::button>
            <x-filament::button x-cloak x-show="state === 'open'" x-on:click="hide()" color="gray" size="sm" icon="heroicon-m-x-mark">
                بستن نمای سه‌بعدی
            </x-filament::button>
        </div>

        <div x-cloak x-show="state === 'open'" class="flex flex-col gap-2">
            <div
                x-ref="stage"
                wire:ignore
                data-scene="{{ json_encode($scene, JSON_UNESCAPED_UNICODE) }}"
                @class([
                    'relative h-96 w-full cursor-grab overflow-hidden rounded-xl border border-gray-200 active:cursor-grabbing dark:border-white/10',
                    'bg-slate-950' => $scene['virtual'],
                    'bg-gradient-to-b from-sky-50 to-gray-100 dark:from-gray-800 dark:to-gray-900' => ! $scene['virtual'],
                ])
            >
                <div x-ref="tooltip" hidden class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-md bg-gray-950/90 px-2 py-1 text-xs text-white shadow"></div>
            </div>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
                @foreach ($scene['statuses'] as $status)
                    <span class="inline-flex items-center gap-1.5">
                        <span class="size-2.5 rounded-full" style="background-color: {{ $status['color'] }}"></span>
                        {{ $status['label'] }} {{ $status['count'] }}
                    </span>
                @endforeach
                <span class="text-gray-400 dark:text-gray-500">جای نفرها نمادین است. برای دیدن نام، روی صندلی بزنید.</span>
            </div>
        </div>

        <p x-cloak x-show="state === 'failed'" class="text-sm text-warning-600 dark:text-warning-400">
            نمای سه‌بعدی در این مرورگر یا دستگاه باز نشد؛ فهرست زیر همان اطلاعات را دارد.
        </p>

        {{-- The same people for screen readers and where the 3D view can't open. --}}
        <ul x-cloak x-bind:class="state === 'failed' ? 'grid grid-cols-1 gap-1 text-sm sm:grid-cols-2' : 'sr-only'">
            @foreach ($scene['seats'] as $seat)
                <li class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $scene['statuses'][$seat['status']]['color'] }}"></span>
                    {{ $seat['name'] }} · {{ $seat['label'] }}
                </li>
            @endforeach
        </ul>
    </div>
@endif
