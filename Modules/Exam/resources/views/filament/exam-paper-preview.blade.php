@if ($paper['error'] !== null)
    <p class="text-sm text-gray-600 dark:text-gray-300" role="status">{{ $paper['error'] }}</p>
@else
    <div class="space-y-3" x-data="{ ready: false }"
        x-init="$nextTick(() => { if ($refs.paper.contentDocument?.URL === 'about:srcdoc' && $refs.paper.contentDocument.readyState === 'complete') ready = true })">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                این نمونه از بانک‌های انتخاب‌شده در فرم تهیه شده است. در آزمون آنلاینِ تصادفی، سؤال‌ها یا ترتیب آن‌ها ممکن است متفاوت باشند.
            </p>
            <x-filament::button type="button" icon="heroicon-o-printer" disabled
                x-bind:disabled="!ready" x-on:click="$refs.paper.contentWindow.print()">
                چاپ آزمون
            </x-filament::button>
        </div>
        <iframe
            class="h-[65vh] w-full rounded-lg border border-gray-200 bg-white dark:border-white/15"
            title="نسخهٔ چاپی آزمون"
            sandbox="allow-same-origin allow-modals"
            x-ref="paper"
            x-on:load="ready = true"
            srcdoc="{{ view('exam::filament.exam-paper', ['paper' => $paper])->render() }}"
        ></iframe>
    </div>
@endif
