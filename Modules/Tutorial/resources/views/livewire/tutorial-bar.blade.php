{{-- The step-by-step tutorial's bar (ADR 0040), a card floating in a corner below
     modals (z-30; Filament modals are above), so it never covers a form. Empty outside tutorial mode. --}}
<div @if ($guide) wire:poll.5s @endif>
    @if ($guide)
        <div
            x-data="{
                open: true,
                min: localStorage.getItem('tutorialBarMin') === '1',
                setMin(value) {
                    this.min = value;
                    localStorage.setItem('tutorialBarMin', value ? '1' : '0');
                    this.$nextTick(() => this.clamp());
                },
                note: null,
                fill(values) {
                    const body = (c, base) => { const v = c.$wire.$get(base); return v && typeof v === 'object' ? v : null; };
                    let filled = 0;
                    for (const c of Livewire.all()) {
                        if (c.name === 'tutorial-bar') { continue; }
                        const actions = c.$wire.mountedActions;
                        const base = Array.isArray(actions) && actions.length ? `mountedActions.${actions.length - 1}.data` : 'data';
                        const state = body(c, base);
                        if (! state) { continue; }
                        for (const [key, value] of Object.entries(values)) {
                            if (key in state) { c.$wire.$set(`${base}.${key}`, value); filled++; }
                        }
                        if (filled) { break; }
                    }
                    this.note = filled ? 'فرم پر شد؛ بررسی کنید و ذخیره را بزنید.' : 'اول فرم یا پنجره‌ی ایجاد را باز کنید (دکمه‌ی «برو به صفحه» و بعد «ایجاد»).';
                    setTimeout(() => this.note = null, 6000);
                },
                pos: JSON.parse(localStorage.getItem('tutorialBarPos') || 'null'),
                drag: null,
                clamp() {
                    if (! this.pos || this.min) { return; }
                    const r = this.$el.getBoundingClientRect();
                    this.pos = {
                        x: Math.min(Math.max(0, this.pos.x), Math.max(0, window.innerWidth - r.width)),
                        y: Math.min(Math.max(0, this.pos.y), Math.max(0, window.innerHeight - r.height)),
                    };
                },
                start(e) {
                    if (this.min || e.button !== 0 || e.target.closest('a, button, input, select, textarea')) { return; }
                    const r = this.$el.getBoundingClientRect();
                    this.drag = { dx: e.clientX - r.left, dy: e.clientY - r.top };
                    this.pos = { x: r.left, y: r.top };
                    this.$el.setPointerCapture(e.pointerId);
                },
                move(e) {
                    if (! this.drag) { return; }
                    this.pos = { x: e.clientX - this.drag.dx, y: e.clientY - this.drag.dy };
                    this.clamp();
                },
                end() {
                    if (! this.drag) { return; }
                    this.drag = null;
                    localStorage.setItem('tutorialBarPos', JSON.stringify(this.pos));
                },
            }"
            x-init="$nextTick(() => clamp())"
            x-on:resize.window="clamp()"
            x-on:pointermove="move($event)"
            x-on:pointerup="end()"
            x-on:pointercancel="end()"
            x-bind:style="pos && ! min ? `left:${pos.x}px;top:${pos.y}px;bottom:auto;right:auto` : ''"
            x-bind:class="min ? 'rounded-full' : 'w-[min(30rem,calc(100vw-2rem))] rounded-2xl'"
            class="fixed bottom-4 left-4 z-30 overflow-hidden border border-gray-200 bg-white text-gray-950 shadow-xl dark:border-white/10 dark:bg-gray-900 dark:text-white">
            <button type="button" x-show="min" x-on:click="setMin(false)" aria-label="باز کردن آموزش" title="باز کردن آموزش"
                class="relative flex h-14 w-14 items-center justify-center text-primary-600 focus-visible:outline-2 focus-visible:outline-offset-[-4px] focus-visible:outline-primary-500 dark:text-primary-400">
                <svg aria-hidden="true" class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m3 9 9-5 9 5-9 5-9-5Zm3 2v6l6 3 6-3v-6M21 9v7" /></svg>
                @if (! $guide['finished'])
                    <span class="absolute end-2 top-2 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary-600 px-1 text-[10px] leading-none tabular-nums text-white">{{ $guide['done'] + 1 }}</span>
                @endif
            </button>

            <div x-show="! min" class="flex max-h-[calc(100dvh-2rem)] flex-col">
                <div x-on:pointerdown="start($event)" x-bind:class="drag ? 'cursor-grabbing' : 'cursor-grab'"
                    title="برای جابه‌جایی، سربرگ را بکشید"
                    class="flex shrink-0 touch-none select-none items-center gap-3 border-b border-primary-100 bg-primary-50 px-4 py-3 dark:border-primary-500/15 dark:bg-primary-500/10">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white text-primary-600 shadow-sm dark:bg-primary-500/15 dark:text-primary-400">
                        <svg aria-hidden="true" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m3 9 9-5 9 5-9 5-9-5Zm3 2v6l6 3 6-3v-6M21 9v7" /></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs text-primary-600 dark:text-primary-400">راهنمای گام‌به‌گام</p>
                        <p class="mt-1 text-sm font-semibold">{{ $guide['scenario'] }}</p>
                    </div>
                    <svg aria-hidden="true" class="h-5 w-5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="currentColor"><circle cx="9" cy="5" r="1.5"/><circle cx="15" cy="5" r="1.5"/><circle cx="9" cy="12" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="9" cy="19" r="1.5"/><circle cx="15" cy="19" r="1.5"/></svg>
                    <button type="button" x-on:click="setMin(true)" title="کوچک کردن" aria-label="کوچک کردن آموزش"
                        class="rounded-lg p-2 text-gray-500 hover:bg-white focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-400 dark:hover:bg-white/10">
                        <svg aria-hidden="true" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" d="M5 12h14" /></svg>
                    </button>
                </div>

                <div class="min-h-0 overflow-y-auto overscroll-contain p-5">
                    <div class="mb-5 space-y-2">
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="font-medium text-primary-600 dark:text-primary-400">{{ $guide['finished'] ? 'آموزش کامل شد' : 'گام '.min($guide['done'] + 1, $guide['total']).' از '.$guide['total'] }}</span>
                            <span class="text-gray-500 dark:text-gray-400">{{ $guide['done'] }} گام انجام‌شده</span>
                        </div>
                        <div role="progressbar" aria-label="پیشرفت آموزش" aria-valuemin="0" aria-valuemax="{{ $guide['total'] }}" aria-valuenow="{{ $guide['done'] }}" class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                            <div class="h-full rounded-full bg-primary-500 transition-all motion-reduce:transition-none" style="width: {{ $guide['total'] ? min(100, $guide['done'] / $guide['total'] * 100) : 0 }}%"></div>
                        </div>
                    </div>

                    @if ($guide['finished'])
                        <p class="text-base font-semibold">همه‌ی گام‌ها انجام شد.</p>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">می‌توانید مراحل طی‌شده را در «همه‌ی گام‌ها» مرور کنید.</p>
                    @else
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="flex-1 text-base font-semibold leading-7">{{ $guide['mission']['title'] }}</h2>
                            <span class="rounded-lg bg-gray-100 px-2.5 py-1 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $guide['mission']['actor'] }}</span>
                        </div>
                        <button type="button" x-on:click="open = ! open; $nextTick(() => clamp())" x-bind:aria-expanded="open"
                            class="mt-3 flex items-center gap-1 text-xs text-gray-500 hover:text-primary-600 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-400">
                            <span x-text="open ? 'بستن جزئیات' : 'نمایش جزئیات'"></span>
                            <svg aria-hidden="true" x-bind:class="open ? 'rotate-180' : ''" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 0 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/></svg>
                        </button>
                        <ol x-show="open" class="mt-3 space-y-3">
                            @foreach ($guide['mission']['steps'] as $step)
                                <li class="flex items-start gap-3 text-sm leading-7 text-gray-600 dark:text-gray-300">
                                    <span aria-hidden="true" class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-medium text-primary-600 dark:bg-primary-500/10 dark:text-primary-400">{{ $loop->iteration }}</span>
                                    <span class="min-w-0 break-words">{{ $step }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    <p x-show="note" x-text="note" role="status" class="mt-4 rounded-xl bg-primary-50 px-3 py-2 text-xs leading-6 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300"></p>
                    @if ($justDone)
                        <p role="status" class="mt-4 rounded-xl bg-success-50 px-3 py-2 text-xs leading-6 text-success-700 dark:bg-success-500/10 dark:text-success-400">{{ $justDone }}</p>
                    @endif

                </div>

                <div class="flex shrink-0 flex-wrap items-center gap-2 border-t border-gray-100 bg-white px-5 py-4 text-sm dark:border-white/10 dark:bg-gray-900">
                    @if (! $guide['finished'] && $guide['mission']['url'])
                        <a href="{{ $guide['mission']['url'] }}" class="rounded-lg bg-primary-600 px-4 py-2.5 font-medium text-white shadow-sm hover:bg-primary-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500">{{ $guide['mission']['urlLabel'] }}</a>
                    @endif
                    @if (! $guide['finished'] && $guide['mission']['fill'])
                        <button type="button" x-on:click="fill(@js($guide['mission']['fill']))" class="rounded-lg bg-success-600 px-3 py-2.5 font-medium text-white shadow-sm hover:bg-success-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-success-500">پر کردن خودکار</button>
                    @endif
                    @if ($guide['actAs'])
                        <form method="POST" action="{{ $guide['actAs']['url'] }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-amber-50 px-3 py-2.5 text-amber-800 hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20">ورود با حساب {{ $guide['actAs']['name'] }}</button>
                        </form>
                    @endif
                    @if ($guide['ownAccountUrl'])
                        <form method="POST" action="{{ $guide['ownAccountUrl'] }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-amber-50 px-3 py-2.5 text-amber-800 hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-amber-500 dark:bg-amber-500/10 dark:text-amber-300 dark:hover:bg-amber-500/20">بازگشت به حساب خودم</button>
                        </form>
                    @endif
                    @unless (\App\Auth\Impersonation::isActive())
                        <a href="{{ $guide['pageUrl'] }}" class="ms-auto rounded-lg px-2 py-2.5 text-xs text-gray-500 hover:text-primary-600 focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-400">همه‌ی گام‌ها</a>
                    @endunless
                </div>
            </div>
        </div>
    @endif
</div>
