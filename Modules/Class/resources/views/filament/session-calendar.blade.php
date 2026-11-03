{{-- A class's sessions on a calendar (SessionsRelationManager, ADR 0047). A session opens its details modal; a day opens the day view. --}}
@php
    $toneClasses = [
        'scheduled' => 'border-info-500 bg-info-50 text-info-950 hover:bg-info-100 dark:bg-info-500/15 dark:text-info-100 dark:hover:bg-info-500/25',
        'live' => 'border-primary-600 bg-primary-100 text-primary-950 ring-2 ring-primary-400/60 hover:bg-primary-200 dark:bg-primary-500/25 dark:text-primary-50',
        'pending' => 'border-warning-500 bg-warning-50 text-warning-950 hover:bg-warning-100 dark:bg-warning-500/15 dark:text-warning-100 dark:hover:bg-warning-500/25',
        'held' => 'border-success-500 bg-success-50 text-success-950 hover:bg-success-100 dark:bg-success-500/15 dark:text-success-100 dark:hover:bg-success-500/25',
        'cancelled' => 'border-danger-400 bg-danger-50/60 text-danger-800 opacity-75 hover:opacity-100 dark:bg-danger-500/10 dark:text-danger-200',
    ];
    $dotClasses = [
        'scheduled' => 'bg-info-500',
        'live' => 'bg-primary-600',
        'pending' => 'bg-warning-500',
        'held' => 'bg-success-500',
        'cancelled' => 'bg-danger-400',
    ];
    // Which tone colours a day of the year view when it has several.
    $tonePriority = ['live', 'pending', 'scheduled', 'held', 'cancelled'];
@endphp

<div class="fi-section session-calendar rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="flex flex-wrap items-center gap-3 border-b border-gray-200 p-4 dark:border-white/10">
        @if ($calendar)
            <div class="flex items-center gap-1">
                <x-filament::icon-button icon="heroicon-m-chevron-right" color="gray" label="قبلی"
                    wire:click="showSessions('{{ $current }}', '{{ $calendar['previous'] }}')" />
                <x-filament::button size="sm" color="gray" outlined
                    wire:click="showSessions('{{ $current }}', '{{ $calendar['today'] }}')">امروز</x-filament::button>
                <x-filament::icon-button icon="heroicon-m-chevron-left" color="gray" label="بعدی"
                    wire:click="showSessions('{{ $current }}', '{{ $calendar['next'] }}')" />
            </div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $calendar['label'] }}</h3>
        @else
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">همه‌ی جلسه‌ها</h3>
        @endif

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-lg bg-gray-100 p-0.5 dark:bg-white/5" role="group" aria-label="نمایش">
                @foreach ($views as $view => $viewLabel)
                    <button type="button" wire:click="showSessions('{{ $view }}')" aria-pressed="{{ $current === $view ? 'true' : 'false' }}"
                        @class([
                            'rounded-md px-3 py-1.5 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                            'bg-white text-primary-700 shadow-sm dark:bg-gray-800 dark:text-primary-300' => $current === $view,
                            'text-gray-600 hover:text-gray-950 dark:text-gray-400 dark:hover:text-white' => $current !== $view,
                        ])>{{ $viewLabel }}</button>
                @endforeach
            </div>

            @if ($calendar)
                {{ $this->addSessionAction }}
            @endif
        </div>
    </div>

    @if ($calendar)
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 px-4 pt-3 text-xs text-gray-600 dark:text-gray-400">
            @foreach ($tones as $tone => $toneLabel)
                <span class="inline-flex items-center gap-1.5">
                    <span class="size-2.5 rounded-full {{ $dotClasses[$tone] }}"></span>
                    {{ $toneLabel }}
                    <span class="font-semibold text-gray-950 dark:text-white">{{ $calendar['totals'][$tone] }}</span>
                </span>
            @endforeach
            <span class="inline-flex items-center gap-1.5">
                <span class="size-2.5 rounded-sm bg-gray-200 dark:bg-white/15"></span>
                تعطیل
            </span>
        </div>

        <div class="p-4" wire:loading.class="opacity-60" wire:target="showSessions">
            @if (in_array($calendar['view'], ['day', 'week'], true))
                {{-- A timeline: hours down, a column per day. --}}
                <div class="overflow-x-auto">
                    <div @class(['grid', 'min-w-[56rem]' => $calendar['view'] === 'week'])
                        style="grid-template-columns: 3.5rem repeat({{ count($calendar['days']) }}, minmax(0, 1fr))">
                        <div></div>
                        @foreach ($calendar['days'] as $day)
                            <button type="button" wire:click="showSessions('day', '{{ $day['date'] }}')"
                                @class([
                                    'flex flex-col items-center gap-0.5 rounded-t-lg border-b border-gray-200 px-1 py-2 text-center dark:border-white/10',
                                    'bg-primary-50 dark:bg-primary-500/10' => $day['is_today'],
                                ])>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $day['weekday'] }}</span>
                                <span @class([
                                    'text-sm font-semibold',
                                    'text-primary-700 dark:text-primary-300' => $day['is_today'],
                                    'text-danger-600 dark:text-danger-400' => ! $day['is_today'] && ($day['is_friday'] || $day['holiday']),
                                    'text-gray-950 dark:text-white' => ! $day['is_today'] && ! $day['is_friday'] && ! $day['holiday'],
                                ])>{{ $day['label'] }}</span>
                                @if ($day['holiday'])
                                    <span class="max-w-full truncate rounded bg-gray-200 px-1.5 text-[11px] text-gray-700 dark:bg-white/15 dark:text-gray-200">{{ $day['holiday'] }}</span>
                                @endif
                            </button>
                        @endforeach

                        <div class="relative" style="height: {{ count($calendar['hours']) * 3.5 }}rem">
                            @foreach ($calendar['hours'] as $index => $hour)
                                <span class="absolute -translate-y-1/2 pe-2 text-[11px] tabular-nums text-gray-500 dark:text-gray-400"
                                    style="top: {{ $index / count($calendar['hours']) * 100 }}%">{{ sprintf('%02d:00', $hour) }}</span>
                            @endforeach
                        </div>
                        @foreach ($calendar['days'] as $day)
                            <div @class([
                                    'relative border-s border-gray-200 dark:border-white/10',
                                    'bg-gray-100/80 dark:bg-white/5' => $day['holiday'],
                                    'bg-primary-50/40 dark:bg-primary-500/5' => $day['is_today'] && ! $day['holiday'],
                                ])
                                style="height: {{ count($calendar['hours']) * 3.5 }}rem">
                                @foreach ($calendar['hours'] as $index => $hour)
                                    <div class="absolute inset-x-0 border-t border-dashed border-gray-200 dark:border-white/5" style="top: {{ $index / count($calendar['hours']) * 100 }}%"></div>
                                @endforeach

                                @foreach ($day['sessions'] as $session)
                                    <button type="button" wire:click="mountAction('sessionDetails', { session: {{ $session['id'] }} })"
                                        title="{{ $session['title'] }} · {{ $session['time'] }} · {{ $session['tone_label'] }}"
                                        class="absolute inset-x-1 z-10 flex min-h-7 flex-col overflow-hidden rounded-md border-s-4 px-2 py-1 text-start text-xs shadow-sm transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $toneClasses[$session['tone']] }}"
                                        style="top: {{ $session['top'] }}%; height: {{ $session['height'] }}%">
                                        @include('class::filament.session-calendar-card', ['session' => $session, 'roomy' => $calendar['view'] === 'day'])
                                    </button>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($calendar['view'] === 'day' && $calendar['days'][0]['sessions'] === [])
                    <p class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
                        {{ $calendar['days'][0]['holiday'] ? 'تعطیل: '.$calendar['days'][0]['holiday'].'؛ ' : '' }}جلسه‌ای در این روز نیست.
                    </p>
                @endif
            @elseif ($calendar['view'] === 'month')
                <div class="overflow-x-auto">
                    <div class="grid min-w-[42rem] grid-cols-7 overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                        @foreach ($calendar['weekdays'] as $weekday)
                            <div class="border-b border-gray-200 bg-gray-50 py-2 text-center text-xs font-medium text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">{{ $weekday }}</div>
                        @endforeach
                        @for ($blank = 0; $blank < $calendar['leading_blanks']; $blank++)
                            <div class="border-b border-e border-gray-200 bg-gray-50/50 dark:border-white/10 dark:bg-white/[0.02]"></div>
                        @endfor
                        @foreach ($calendar['days'] as $day)
                            <div @class([
                                'flex min-h-28 flex-col gap-1 border-b border-e border-gray-200 p-1.5 dark:border-white/10',
                                'bg-gray-100/80 dark:bg-white/5' => $day['holiday'],
                                'bg-primary-50/50 dark:bg-primary-500/10' => $day['is_today'],
                            ])>
                                <div class="flex items-center justify-between gap-1">
                                    <button type="button" wire:click="showSessions('day', '{{ $day['date'] }}')" title="{{ $day['label'] }}"
                                        @class([
                                            'grid size-7 place-items-center rounded-full text-sm font-semibold tabular-nums hover:bg-gray-200 dark:hover:bg-white/10',
                                            'bg-primary-600 text-white hover:bg-primary-700 dark:hover:bg-primary-500' => $day['is_today'],
                                            'text-danger-600 dark:text-danger-400' => ! $day['is_today'] && ($day['is_friday'] || $day['holiday']),
                                            'text-gray-800 dark:text-gray-200' => ! $day['is_today'] && ! $day['is_friday'] && ! $day['holiday'],
                                        ])>{{ $day['day_number'] }}</button>
                                    @if ($day['holiday'])
                                        <span class="truncate text-[11px] text-gray-600 dark:text-gray-300" title="{{ $day['holiday'] }}">{{ $day['holiday'] }}</span>
                                    @endif
                                </div>
                                @foreach (array_slice($day['sessions'], 0, 3) as $session)
                                    <button type="button" wire:click="mountAction('sessionDetails', { session: {{ $session['id'] }} })"
                                        title="{{ $session['title'] }} · {{ $session['time'] }} · {{ $session['tone_label'] }}"
                                        class="flex items-center gap-1 truncate rounded border-s-4 px-1.5 py-0.5 text-start text-[11px] transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $toneClasses[$session['tone']] }}">
                                        <span class="shrink-0 font-semibold tabular-nums">{{ \Illuminate\Support\Str::before($session['time'], '–') }}</span>
                                        <span @class(['truncate', 'line-through' => $session['tone'] === 'cancelled'])>{{ $session['title'] }}</span>
                                    </button>
                                @endforeach
                                @if (count($day['sessions']) > 3)
                                    <button type="button" wire:click="showSessions('day', '{{ $day['date'] }}')"
                                        class="text-start text-[11px] font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ count($day['sessions']) - 3 }} جلسه‌ی دیگر
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                {{-- Year: twelve small months; a day with sessions is coloured and opens the day view. --}}
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($calendar['months'] as $month)
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                            <button type="button" wire:click="showSessions('month', '{{ $month['date'] }}')"
                                class="mb-2 flex w-full items-center justify-between text-sm font-semibold text-gray-950 hover:text-primary-600 dark:text-white dark:hover:text-primary-400">
                                <span>{{ $month['label'] }}</span>
                                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">{{ $month['count'] ? $month['count'].' جلسه' : '' }}</span>
                            </button>
                            <div class="grid grid-cols-7 gap-0.5 text-center text-[11px]">
                                @foreach ($calendar['weekdays'] as $weekday)
                                    <span class="pb-1 text-gray-400 dark:text-gray-500">{{ mb_substr($weekday, 0, 1) }}</span>
                                @endforeach
                                @for ($blank = 0; $blank < $month['leading_blanks']; $blank++)
                                    <span></span>
                                @endfor
                                @foreach ($month['days'] as $day)
                                    @php
                                        $tone = collect($tonePriority)->first(fn (string $tone): bool => isset($day['tones'][$tone]));
                                    @endphp
                                    @if ($day['count'] > 0)
                                        <button type="button" wire:click="showSessions('day', '{{ $day['date'] }}')"
                                            title="{{ collect($day['tones'])->map(fn (int $count, string $tone): string => $tones[$tone].' '.$count)->join('، ') }}{{ $day['holiday'] ? ' · '.$day['holiday'] : '' }}"
                                            @class([
                                                'relative grid aspect-square place-items-center rounded font-semibold tabular-nums text-white transition hover:opacity-80 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500',
                                                $dotClasses[$tone],
                                                'ring-2 ring-gray-950 ring-offset-1 dark:ring-white dark:ring-offset-gray-900' => $day['is_today'],
                                            ])>
                                            {{ $day['day_number'] }}
                                            @if ($day['count'] > 1)
                                                <span class="absolute -top-1 -end-1 grid size-3.5 place-items-center rounded-full bg-gray-950 text-[9px] text-white dark:bg-white dark:text-gray-950">{{ $day['count'] }}</span>
                                            @endif
                                        </button>
                                    @else
                                        <span title="{{ $day['holiday'] }}" @class([
                                            'grid aspect-square place-items-center rounded tabular-nums',
                                            'bg-gray-200 dark:bg-white/15' => $day['holiday'],
                                            'text-danger-500 dark:text-danger-400' => $day['is_friday'] || $day['holiday'],
                                            'text-gray-600 dark:text-gray-300' => ! $day['is_friday'] && ! $day['holiday'],
                                            'ring-2 ring-primary-500' => $day['is_today'],
                                        ])>{{ $day['day_number'] }}</span>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- The table renders the action modals; without it, the calendar does. --}}
        <x-filament-actions::modals />
    @endif
</div>
