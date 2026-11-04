{{-- A session on the day / week timeline; $roomy adds the place; $showsClassroom / $showsTeacher its class and teacher; a learner's calendar adds their attendance. --}}
<span class="flex items-center gap-1 font-semibold">
    <span @class(['truncate', 'line-through' => $session['tone'] === 'cancelled'])>{{ $session['title'] }}</span>
    @if ($session['tone'] === 'live')
        <span class="size-2 shrink-0 animate-pulse rounded-full bg-primary-600 motion-reduce:animate-none"></span>
    @endif
</span>
<span class="flex flex-wrap items-center gap-x-1.5 gap-y-0.5 opacity-80">
    <span class="tabular-nums">{{ $session['time'] }}</span>
    @include('class::filament.session-calendar-mode', ['mode' => $session['mode']])
    @if ($session['kind'])
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
        $showsTeacher && $session['teacher'] ? $session['teacher'].($session['is_substitute'] ? ' (جایگزین)' : '') : null,
        ! $showsTeacher && $session['is_substitute'] ? '(جایگزین)' : null,
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
