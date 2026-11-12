{{-- A session on the day / week timeline; $roomy adds the place; $showsClassroom / $showsTeacher its class and teacher; a learner's calendar adds their attendance. --}}
<span class="flex items-center gap-1 font-semibold">
    @if ($session['is_exam'])
        <span class="inline-flex shrink-0 items-center gap-0.5 rounded bg-fuchsia-700 px-1 text-[10px] font-semibold text-white dark:bg-fuchsia-300 dark:text-fuchsia-950">
            <x-filament::icon icon="heroicon-m-clipboard-document-list" class="size-3" />
            امتحانی
        </span>
    @endif
    <span @class(['truncate', 'line-through' => $session['tone'] === 'cancelled'])>{{ $session['title'] }}</span>
    @if ($session['tone'] === 'live')
        <span class="relative flex size-3 shrink-0" title="در حال برگزاری">
            <span class="absolute inline-flex size-full animate-ping rounded-full bg-primary-500 opacity-75 motion-reduce:animate-none"></span>
            <span class="relative inline-flex size-3 animate-pulse rounded-full bg-primary-600 shadow-[0_0_6px_2px] shadow-primary-500/70 motion-reduce:animate-none"></span>
        </span>
    @endif
</span>
<span class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 opacity-80">
    <span class="tabular-nums">{{ $session['time'] }}</span>
    @include('class::filament.session-calendar-mode', ['mode' => $session['mode']])
    @if ($session['kind'] && ! $session['is_exam'])
        <span class="rounded bg-white/70 px-1 text-[10px] font-medium dark:bg-black/30">{{ $session['kind'] }}</span>
    @endif
    @if ($session['was_moved'])
        <x-filament::icon icon="heroicon-m-arrows-right-left" class="size-3.5" title="جابه‌جاشده" />
    @endif
    @if ($session['has_attendance'])
        <x-filament::icon icon="heroicon-m-clipboard-document-check" class="size-3.5" title="حضور و غیاب ثبت شده" />
    @endif
    @if ($session['has_homework'])
        <x-filament::icon icon="heroicon-m-book-open" class="size-3.5" title="تکلیف دارد" />
    @endif
</span>
@php
    $line = array_filter([
        $showsClassroom ? $session['classroom'] : null,
        $showsTeacher ? $session['teacher'] : null,
        ! $showsTeacher && ($session['is_substitute'] ?? false) ? '(جایگزین)' : null,
        $roomy ? $session['location'] : null,
    ]);
@endphp
@if ($line !== [])
    <span class="truncate opacity-80">{{ implode(' · ', $line) }}</span>
@endif
@isset($session['attendance'])
    <span class="font-semibold">{{ $session['attendance']->getLabel() }}</span>
@endisset
@if ($roomy && $session['cancel_reason'])
    <span class="truncate opacity-80">دلیل لغو: {{ $session['cancel_reason'] }}</span>
@endif
