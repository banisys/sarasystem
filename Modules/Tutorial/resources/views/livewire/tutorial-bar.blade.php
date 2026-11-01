{{-- The step-by-step tutorial's bar (ADR 0040), a card floating in a corner below
     modals (z-30; Filament modals are above), so it never covers a form. Empty outside tutorial mode. --}}
<div @if ($guide) wire:poll.5s @endif>
    @if ($guide)
        <div
            x-data="{
                open: true,
                min: localStorage.getItem('tutorialBarMin') === '1',
                moved: false,
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
                    this.moved = false;
                    this.drag = { dx: e.clientX - r.left, dy: e.clientY - r.top, sx: e.clientX, sy: e.clientY };
                    this.pos = { x: r.left, y: r.top };
                    this.$el.setPointerCapture(e.pointerId);
                },
                move(e) {
                    if (! this.drag) { return; }
                    if (Math.abs(e.clientX - this.drag.sx) + Math.abs(e.clientY - this.drag.sy) > 4) { this.moved = true; }
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
            x-on:pointerdown="start($event)"
            x-on:pointermove="move($event)"
            x-on:pointerup="end()"
            x-on:pointercancel="end()"
            x-bind:style="pos && ! min ? `left:${pos.x}px;top:${pos.y}px;bottom:auto;right:auto` : ''"
            x-bind:class="[min ? 'cursor-pointer rounded-full p-3' : (drag ? 'cursor-grabbing select-none' : 'cursor-grab'), min ? '' : 'w-[min(42rem,calc(100vw-2rem))] rounded-xl px-6 py-5']"
            x-bind:title="min ? 'باز کردن آموزش' : 'با کشیدن جابه‌جا کنید'"
            class="fixed bottom-4 left-4 z-30 touch-none border border-primary-200 bg-primary-50 text-lg text-primary-950 shadow-lg dark:border-primary-500/30 dark:bg-primary-950 dark:text-primary-50">
            <div x-show="min" x-on:click="setMin(false)" class="relative flex h-8 w-8 items-center justify-center">
                <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 7.74-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5" /></svg>
                @if (! $guide['finished'])
                    <span class="absolute -end-1 -top-1 rounded-full bg-primary-600 px-1.5 text-[10px] leading-4 text-white">{{ $guide['done'] + 1 }}</span>
                @endif
            </div>

            <div x-show="! min" class="flex flex-wrap items-center gap-x-4 gap-y-2 pl-8">
                <button type="button" x-on:click="setMin(true)" title="کوچک کردن" class="absolute left-2 top-2 rounded-md p-1 hover:bg-black/10 dark:hover:bg-white/10">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" /></svg>
                </button>
                <span class="font-semibold">آموزش «{{ $guide['scenario'] }}» · {{ min($guide['done'] + 1, $guide['total']) }} از {{ $guide['total'] }}</span>

                @if ($guide['finished'])
                    <span>همه‌ی گام‌ها انجام شد.</span>
                @else
                    <span>{{ $guide['mission']['title'] }} <span class="text-xs opacity-75">({{ $guide['mission']['actor'] }})</span></span>
                    <button type="button" class="text-xs underline" x-on:click="open = ! open" x-text="open ? 'بستن' : 'جزئیات'"></button>
                @endif

                <span x-show="note" x-text="note" class="w-full rounded-md bg-white/60 px-2 py-1 text-xs dark:bg-black/20"></span>

                @if ($justDone)
                    <span class="rounded-md bg-success-500 px-2 py-0.5 text-xs text-white">{{ $justDone }}</span>
                @endif

                <div class="flex w-full flex-wrap items-center gap-2">
                    @if (! $guide['finished'] && $guide['mission']['url'])
                        <a href="{{ $guide['mission']['url'] }}" class="rounded-md bg-primary-600 px-3 py-1 text-xs font-medium text-white hover:bg-primary-700">{{ $guide['mission']['urlLabel'] }}</a>
                    @endif
                    @if (! $guide['finished'] && $guide['mission']['fill'])
                        <button type="button" x-on:click="fill(@js($guide['mission']['fill']))" class="rounded-md bg-success-600 px-3 py-1 text-xs font-medium text-white hover:bg-success-700">پر کردن خودکار</button>
                    @endif
                    @if ($guide['actAs'])
                        <form method="POST" action="{{ $guide['actAs']['url'] }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600">ورود با حساب {{ $guide['actAs']['name'] }}</button>
                        </form>
                    @endif
                    @if ($guide['ownAccountUrl'])
                        <form method="POST" action="{{ $guide['ownAccountUrl'] }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600">بازگشت به حساب خودم</button>
                        </form>
                    @endif
                    @unless (\App\Auth\Impersonation::isActive())
                        <a href="{{ $guide['pageUrl'] }}" class="text-xs underline">همه‌ی گام‌ها</a>
                    @endunless
                </div>
            </div>

            @if (! $guide['finished'])
                <ul x-show="open && ! min" class="mt-3 list-disc ps-6 text-base leading-8">
                    @foreach ($guide['mission']['steps'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
