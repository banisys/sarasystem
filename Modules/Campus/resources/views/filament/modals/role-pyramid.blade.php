{{-- The roles' pyramid of power (ADR 0057). Its script loads only when the modal opens. --}}
<div
    x-data="{
        state: 'loading',
        scene: null,
        selected: null,
        autoRotate: false,
        names: true,
        async init() {
            try {
                const { mountRoleScene } = await import(@js(\Illuminate\Support\Facades\Vite::asset('Modules/Campus/resources/js/role-scene.js')));

                this.state = 'open';
                await this.$nextTick();
                this.scene = mountRoleScene(this.$refs.stage, this.$refs.tooltip, JSON.parse(this.$refs.stage.dataset.scene), (role) => { this.selected = role; });
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
            {{-- The picked role. --}}
            <div x-cloak x-show="selected" class="absolute start-3 top-3 z-20 max-h-[calc(100%-1.5rem)] w-80 max-w-[calc(100%-1.5rem)] overflow-y-auto rounded-xl border border-gray-200 bg-white p-4 text-sm shadow-lg dark:border-white/10 dark:bg-gray-900">
                <template x-if="selected">
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-semibold text-gray-950 dark:text-white" x-text="selected.label"></h3>
                            <x-filament::icon-button x-on:click="closeCard()" icon="heroicon-o-x-mark" color="gray" size="sm" label="بستن" />
                        </div>
                        <p class="text-gray-600 dark:text-gray-300">
                            سطح <span x-text="selected.level + 1"></span> از {{ $scene['levels'] }} ·
                            <span x-text="selected.holders"></span> نفر<template x-if="selected.tenant"><span> · فقط در <span x-text="selected.tenant"></span></span></template>
                        </p>
                        <div>
                            <div class="flex justify-between text-xs text-gray-600 dark:text-gray-300">
                                <span x-text="selected.super ? 'همه‌ی مجوزها، و بدون هیچ بررسی' : selected.permissions + ' مجوز از {{ $scene['totalPermissions'] }}'"></span>
                                <span x-text="selected.share + '٪'"></span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                                <div class="h-full rounded-full bg-primary-600" x-bind:style="`width: ${selected.share}%`"></div>
                            </div>
                        </div>
                        <template x-if="selected.aboveLabels.length"><p class="text-gray-950 dark:text-white"><span class="text-amber-600 dark:text-amber-400">بالادست:</span> <span x-text="selected.aboveLabels.join('، ')"></span></p></template>
                        <template x-if="selected.belowLabels.length"><p class="text-gray-950 dark:text-white"><span class="text-blue-600 dark:text-blue-400">زیردست:</span> <span x-text="selected.belowLabels.join('، ')"></span></p></template>
                        <template x-if="selected.peerLabels.length"><p class="text-gray-950 dark:text-white"><span class="text-emerald-600 dark:text-emerald-400">هم‌تراز:</span> <span x-text="selected.peerLabels.join('، ')"></span></p></template>
                        <p x-show="selected.belowIds.length" class="text-xs text-gray-500 dark:text-gray-400">دارنده‌ی این نقش می‌تواند <span x-text="selected.belowIds.length"></span> نقش پایین‌تر را به دیگران بدهد.</p>
                        <template x-if="selected.areas.length">
                            <div class="flex flex-col gap-1 border-t border-gray-100 pt-2 dark:border-white/10">
                                <span class="text-xs font-medium text-gray-950 dark:text-white">دسترسی به بخش‌ها</span>
                                <template x-for="area in selected.areas" x-bind:key="area.label">
                                    <div class="flex justify-between gap-2 text-xs text-gray-600 dark:text-gray-300"><span x-text="area.label"></span><span x-text="area.count"></span></div>
                                </template>
                            </div>
                        </template>
                        <a x-bind:href="selected.url" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline dark:text-primary-400">ویرایش نقش</a>
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

        @include('campus::filament.partials.scene-toolbar')

        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-300">
            <span>طبقه‌ی بالاتر: جایگاه بالاتر</span>
            <span class="inline-flex items-center gap-1.5">ستون بلندتر و پررنگ‌تر: مجوز بیشتر <span class="h-2.5 w-12 rounded-sm" style="background: linear-gradient(to left, #bfdbfe, #6d28d9)"></span></span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background-color: #f59e0b"></span> مدیر کل</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background-color: #cbd5e1"></span> بدون مجوز مدیریتی</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm" style="background-color: #10b981"></span> هم‌تراز</span>
            <span>آدمک‌ها: دارندگان نقش (حداکثر ۱۰)</span>
        </div>
        <p class="text-xs text-gray-400 dark:text-gray-500">جایگاه از خود مجوزها به دست می‌آید: نقشی بالاتر است که همه‌ی مجوزهای نقش پایین‌تر را داشته باشد و بیشتر هم داشته باشد. دو نقش در یک طبقه لزوماً هم‌تراز نیستند. کلیک روی ستون: جزئیات و بالادست و زیردست</p>
    </div>

    <p x-cloak x-show="state === 'failed'" class="text-sm text-warning-600 dark:text-warning-400">
        نمای سه‌بعدی در این مرورگر یا دستگاه باز نشد؛ فهرست زیر همان اطلاعات را دارد.
    </p>

    {{-- The same roles for screen readers and where the 3D view can't open. --}}
    <ul x-cloak x-bind:class="state === 'failed' ? 'grid grid-cols-1 gap-1 text-sm sm:grid-cols-2' : 'sr-only'">
        @foreach ($scene['roles'] as $role)
            <li>سطح {{ $role['level'] + 1 }}: {{ $role['label'] }} · {{ $role['permissions'] }} مجوز · {{ $role['holders'] }} نفر</li>
        @endforeach
    </ul>
</div>
