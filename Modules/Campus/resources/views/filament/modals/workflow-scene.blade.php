{{-- A workflow in 3D (ADR 0064). Its script loads only when the modal opens. --}}
@php
    $toneColors = [
        'approve' => 'text-success-700 dark:text-success-400',
        'reject' => 'text-danger-700 dark:text-danger-400',
        'return' => 'text-warning-700 dark:text-warning-400',
        'edit' => 'text-info-700 dark:text-info-400',
    ];
    $finishText = 'پایان فرایند: '.$scene['finish'];
    $startText = 'شروع: '.$scene['subjectLabel'].' ثبت می‌شود و پرونده به مرحله‌ی اول می‌رود.';
    $steps = collect($scene['steps']);
@endphp
@if ($scene['steps'] === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">این فرایند هنوز مرحله‌ای ندارد.</p>
@else
<div
    x-data="{
        state: 'loading',
        scene: null,
        selected: null,
        stop: null,
        playing: false,
        autoRotate: false,
        names: @js(count($scene['steps']) <= 12),
        tones: @js($toneColors),
        startText: @js($startText),
        finishText: @js($finishText),
        async init() {
            try {
                const { mountWorkflowScene: mount } = await import(@js(\Illuminate\Support\Facades\Vite::asset('Modules/Campus/resources/js/workflow-scene.js')));

                this.state = 'open';
                // The modal is still opening; the path is fitted to the stage's real size.
                await new Promise((ready) => {
                    const sizes = new ResizeObserver(() => {
                        if (this.$refs.stage.clientWidth > 0) {
                            sizes.disconnect();
                            ready();
                        }
                    });
                    sizes.observe(this.$refs.stage);
                });
                this.scene = mount(this.$refs.stage, this.$refs.tooltip, JSON.parse(this.$refs.stage.dataset.scene), {
                    onSelect: (picked) => { this.selected = picked; },
                    onPlay: (stop) => {
                        this.stop = stop;
                        this.playing = stop !== null;
                    },
                });
                this.scene.setNames(this.names);
            } catch (error) {
                console.error(error);
                this.state = 'failed';
            }
        },
        togglePlay() {
            this.playing ? this.scene.stopPlaying() : this.scene.play();
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
            {{-- Where the played case is. --}}
            <div x-cloak x-show="stop" role="status" class="absolute inset-x-3 top-3 z-20 mx-auto max-w-2xl rounded-xl border border-gray-200 bg-white/95 p-3 text-sm shadow-lg dark:border-white/10 dark:bg-gray-900/95">
                <template x-if="stop">
                    <div class="flex flex-col gap-1">
                        <template x-if="stop.kind === 'start'"><p class="font-semibold text-gray-950 dark:text-white" x-text="startText"></p></template>
                        <template x-if="stop.kind === 'finish'"><p class="font-semibold text-gray-950 dark:text-white" x-text="finishText"></p></template>
                        <template x-if="stop.kind === 'step'">
                            <div class="flex flex-col gap-1">
                                <p class="font-semibold text-gray-950 dark:text-white">مرحله‌ی <span x-text="stop.number"></span> از {{ count($scene['steps']) }}: <span x-text="stop.name"></span></p>
                                <p class="text-gray-600 dark:text-gray-300">مسئول: <span x-text="stop.assignee"></span></p>
                                <template x-for="outcome in stop.outcomes" x-bind:key="outcome.tone">
                                    <p class="text-xs" x-bind:class="tones[outcome.tone]" x-text="outcome.text"></p>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            {{-- What was clicked. --}}
            <div x-cloak x-show="selected" class="absolute start-3 top-3 z-20 max-h-[calc(100%-1.5rem)] w-80 max-w-[calc(100%-1.5rem)] overflow-y-auto rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-lg dark:border-white/10 dark:bg-gray-900">
                <template x-if="selected">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-semibold text-gray-950 dark:text-white" x-text="{ step: `مرحله‌ی ${selected.number}: ${selected.name}`, lane: selected.name, start: 'شروع', finish: 'پایان' }[selected.kind]"></h3>
                            <x-filament::icon-button x-on:click="closeCard()" icon="heroicon-o-x-mark" color="gray" size="sm" label="بستن" />
                        </div>
                        <template x-if="selected.kind === 'start'"><p class="text-gray-600 dark:text-gray-300" x-text="startText"></p></template>
                        <template x-if="selected.kind === 'finish'"><p class="text-gray-600 dark:text-gray-300" x-text="finishText"></p></template>
                        <template x-if="selected.kind === 'lane'">
                            <div class="flex flex-col gap-1">
                                <p class="text-gray-600 dark:text-gray-300">مرحله‌های این فرایند در این {{ config('sarasystem.tenant_label') }}:</p>
                                <template x-for="name in selected.steps" x-bind:key="name"><p class="text-gray-950 dark:text-white" x-text="name"></p></template>
                            </div>
                        </template>
                        <template x-if="selected.kind === 'step'">
                            <div class="flex flex-col gap-2">
                                <p class="text-gray-950 dark:text-white"><span class="text-gray-500 dark:text-gray-400">مسئول:</span> <span x-text="selected.assignee"></span></p>
                                <p x-show="selected.openCases > 0" class="text-gray-950 dark:text-white"><span x-text="selected.openCases"></span> پرونده اکنون در این مرحله است</p>
                                <div class="flex flex-col gap-1 border-t border-gray-100 pt-2 dark:border-white/10">
                                    <template x-for="outcome in selected.outcomes" x-bind:key="outcome.tone">
                                        <p x-bind:class="tones[outcome.tone]" x-text="outcome.text"></p>
                                    </template>
                                </div>
                                <template x-if="selected.notes.length">
                                    <div class="flex flex-col gap-1 border-t border-gray-100 pt-2 dark:border-white/10">
                                        <span class="text-xs font-medium text-gray-950 dark:text-white">پیش از تأیید</span>
                                        <template x-for="note in selected.notes" x-bind:key="note"><p class="text-xs text-gray-600 dark:text-gray-300" x-text="note"></p></template>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div
                x-ref="stage"
                wire:ignore
                data-scene="{{ json_encode($scene, JSON_UNESCAPED_UNICODE) }}"
                class="relative h-[calc(100vh-12rem)] min-h-80 cursor-grab overflow-hidden rounded-xl border border-gray-200 bg-gradient-to-b from-sky-50 to-gray-100 active:cursor-grabbing dark:border-white/10 dark:from-gray-800 dark:to-gray-900"
            >
                <div x-ref="tooltip" hidden class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-full whitespace-nowrap rounded-md bg-gray-950/90 px-2 py-1 text-xs text-white shadow"></div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <x-filament::button x-on:click="togglePlay()" size="xs">
                <span class="inline-flex items-center gap-1">
                    <x-filament::icon x-show="! playing" icon="heroicon-m-play" class="size-4" />
                    <x-filament::icon x-cloak x-show="playing" icon="heroicon-m-stop" class="size-4" />
                    <span x-text="playing ? 'توقف' : 'پخش مسیر'">پخش مسیر</span>
                </span>
            </x-filament::button>
            @include('campus::filament.partials.scene-toolbar')
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
            <span>از راست به چپ، هر مرحله یک پله بالاتر</span>
            @foreach ($scene['lanes'] as $lane)
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background-color: {{ $lane['color'] }}"></span> {{ $lane['name'] }}</span>
            @endforeach
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background-color: #16a34a"></span> تأیید</span>
            @if ($steps->contains('rejects', true))
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background-color: #dc2626"></span> رد: پایان پرونده</span>
            @endif
            @if ($steps->contains('returns', true))
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background-color: #f59e0b"></span> برگشت (با کلیک روی مرحله، همه‌ی مسیرهای برگشت)</span>
            @endif
            @if ($steps->contains('edits', true))
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background-color: #2563eb"></span> پرچم: ویرایش اطلاعات</span>
            @endif
            @if ($steps->contains(fn (array $step): bool => $step['notes'] !== []))
                <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background-color: #7c3aed"></span> تابلو: کار پیش از تأیید</span>
            @endif
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background-color: #fcd34d"></span> پرونده‌های در جریان: {{ $scene['openCases'] }}</span>
            <span>یک آدمک: شخص · سه آدمک: نقش</span>
        </div>
    </div>

    <p x-cloak x-show="state === 'failed'" class="text-sm text-warning-600 dark:text-warning-400">
        نمای سه‌بعدی در این مرورگر یا دستگاه باز نشد؛ فهرست زیر همان اطلاعات را دارد.
    </p>

    {{-- The same steps for screen readers and where the 3D view can't open. --}}
    <ol x-cloak x-bind:class="state === 'failed' ? 'flex flex-col gap-2 text-sm' : 'sr-only'">
        @foreach ($scene['steps'] as $step)
            <li>
                {{ $step['number'] }}. {{ $step['name'] }}: {{ $step['assignee'] }}{{ $step['openCases'] > 0 ? ' · '.$step['openCases'].' پرونده' : '' }}
                <ul>
                    @foreach ($step['outcomes'] as $outcome)
                        <li class="text-xs text-gray-600 dark:text-gray-300">{{ $outcome['text'] }}</li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ol>
</div>
@endif
