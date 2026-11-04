{{-- How fresh the 3D page's windows are (ADR 0053); the page's Alpine data has `time` and `stale`. --}}
<span class="inline-flex items-center gap-1.5" x-bind:class="stale && 'text-warning-600 dark:text-warning-400'">
    @if ($liveSeconds)
        <span x-show="! stale" class="size-2 animate-pulse rounded-full bg-success-500 motion-reduce:animate-none"></span>
    @endif
    <span x-show="! stale">وضعیت ساعت <span x-text="time"></span>@if ($liveSeconds)؛ خودکار به‌روز می‌شود @endif</span>
    <span x-cloak x-show="stale">به‌روزرسانی نشد؛ آخرین وضعیت ساعت <span x-text="time"></span></span>
</span>
