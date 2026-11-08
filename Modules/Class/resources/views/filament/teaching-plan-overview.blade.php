{{-- A teaching plan as a picture (ADR 0071); data from TeachingPlanOverview. --}}
@php
    /** @var array $overview */
    $palette = [
        ['cell' => 'bg-violet-500 text-white', 'soft' => 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/15 dark:text-violet-300 dark:ring-violet-500/30', 'dot' => 'bg-violet-500'],
        ['cell' => 'bg-sky-500 text-white', 'soft' => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/30', 'dot' => 'bg-sky-500'],
        ['cell' => 'bg-emerald-500 text-white', 'soft' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30', 'dot' => 'bg-emerald-500'],
        ['cell' => 'bg-amber-500 text-white', 'soft' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30', 'dot' => 'bg-amber-500'],
        ['cell' => 'bg-rose-500 text-white', 'soft' => 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30', 'dot' => 'bg-rose-500'],
        ['cell' => 'bg-teal-500 text-white', 'soft' => 'bg-teal-50 text-teal-700 ring-teal-200 dark:bg-teal-500/15 dark:text-teal-300 dark:ring-teal-500/30', 'dot' => 'bg-teal-500'],
        ['cell' => 'bg-indigo-500 text-white', 'soft' => 'bg-indigo-50 text-indigo-700 ring-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/30', 'dot' => 'bg-indigo-500'],
        ['cell' => 'bg-orange-500 text-white', 'soft' => 'bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-500/15 dark:text-orange-300 dark:ring-orange-500/30', 'dot' => 'bg-orange-500'],
    ];
    $color = fn (int $index): array => $palette[$index % count($palette)];
    $plan = $overview['plan'];
    $totals = $overview['totals'];
@endphp

<div class="space-y-6 text-sm">
    {{-- Facts --}}
    <div class="space-y-3">
        <div class="flex flex-wrap gap-2">
            <span class="rounded-lg bg-gray-100 px-3 py-1.5 text-gray-700 dark:bg-white/10 dark:text-gray-200">درس: <strong>{{ $plan['subject'] ?? 'همه' }}</strong></span>
            <span class="rounded-lg bg-gray-100 px-3 py-1.5 text-gray-700 dark:bg-white/10 dark:text-gray-200"><strong>{{ $totals['topics'] }}</strong> سرفصل</span>
            <span class="rounded-lg bg-gray-100 px-3 py-1.5 text-gray-700 dark:bg-white/10 dark:text-gray-200"><strong>{{ $totals['sessions'] }}</strong> جلسه</span>
            <span class="rounded-lg bg-gray-100 px-3 py-1.5 text-gray-700 dark:bg-white/10 dark:text-gray-200"><strong>{{ $totals['classes'] }}</strong> کلاس پیرو</span>
            <span @class([
                'rounded-lg px-3 py-1.5',
                'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $plan['is_active'],
                'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' => ! $plan['is_active'],
            ])>{{ $plan['is_active'] ? 'فعال' : 'غیرفعال' }}</span>
        </div>
        @if (filled($plan['description']))
            <p class="whitespace-pre-line text-gray-600 dark:text-gray-300">{{ $plan['description'] }}</p>
        @endif
    </div>

    @if ($overview['topics'] === [])
        <p class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-gray-500 dark:border-white/15 dark:text-gray-400">این طرح هنوز سرفصلی ندارد.</p>
    @else
        {{-- Session map: one cell per session, grouped and coloured by topic. --}}
        <section class="space-y-2">
            <h3 class="font-semibold text-gray-900 dark:text-white">نقشه‌ی جلسه‌ها</h3>
            <p class="text-xs text-gray-500 dark:text-gray-400">هر خانه یک جلسه است، به ترتیب از جلسه‌ی ۱؛ رنگ هر گروه سرفصل آن جلسه‌هاست.</p>
            <div class="flex flex-wrap gap-x-3 gap-y-3 rounded-xl border border-gray-200 p-3 dark:border-white/10">
                @foreach ($overview['topics'] as $index => $topic)
                    <div class="min-w-0 space-y-1" title="{{ $topic['number'] }}. {{ $topic['title'] }}">
                        <div class="flex max-w-40 items-center gap-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                            <span class="shrink-0 rounded px-1 {{ $color($index)['soft'] }}">{{ $topic['number'] }}</span>
                            <span class="truncate" dir="auto">{{ $topic['title'] }}</span>
                        </div>
                        <div class="flex flex-wrap gap-1">
                            @for ($session = $topic['from']; $session <= $topic['to']; $session++)
                                <span class="flex size-8 items-center justify-center rounded-md text-xs font-semibold {{ $color($index)['cell'] }}">{{ $session }}</span>
                            @endfor
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Topics in order, as a path. --}}
        <section class="space-y-2">
            <h3 class="font-semibold text-gray-900 dark:text-white">سرفصل‌ها به ترتیب</h3>
            <ol class="relative space-y-3 border-s-2 border-gray-200 ps-6 dark:border-white/10">
                @foreach ($overview['topics'] as $index => $topic)
                    <li class="relative">
                        <span class="absolute -start-[2.15rem] top-1.5 flex size-6 items-center justify-center rounded-full text-xs font-bold ring-4 ring-white dark:ring-gray-900 {{ $color($index)['cell'] }}">{{ $topic['number'] }}</span>
                        <div class="rounded-xl p-3 ring-1 {{ $color($index)['soft'] }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="font-semibold" dir="auto">{{ $topic['title'] }}</span>
                                <span class="text-xs">
                                    {{ $topic['sessions'] }} جلسه ·
                                    {{ $topic['from'] === $topic['to'] ? "جلسه‌ی {$topic['from']}" : "جلسه‌های {$topic['from']} تا {$topic['to']}" }}
                                </span>
                            </div>
                            @if (filled($topic['description']))
                                <p class="mt-1 whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $topic['description'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- Classes following the plan, each with the topics it covered. --}}
    <section class="space-y-2">
        <h3 class="font-semibold text-gray-900 dark:text-white">کلاس‌هایی که از این طرح پیروی می‌کنند</h3>
        @forelse ($overview['classes'] as $class)
            <div class="space-y-2 rounded-xl border border-gray-200 p-3 dark:border-white/10">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $class['title'] }}</span>
                        @if ($class['tenant'])
                            <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $class['tenant'] }}</span>
                        @endif
                    </div>
                    <span class="text-xs text-gray-600 dark:text-gray-300">پیشرفت: <strong>{{ $class['covered'] }}</strong> از {{ $totals['topics'] }} سرفصل</span>
                </div>

                @if ($overview['topics'] !== [])
                    <div class="flex h-3 gap-0.5 overflow-hidden rounded-full" role="img" aria-label="پیشرفت {{ $class['title'] }}">
                        @foreach ($overview['topics'] as $index => $topic)
                            <span
                                style="flex-grow: {{ $topic['sessions'] }}"
                                title="{{ $topic['number'] }}. {{ $topic['title'] }}{{ in_array($topic['id'], $class['covered_ids'], true) ? ' (تمام شده)' : '' }}"
                                @class([
                                    'basis-0',
                                    $color($index)['dot'] => in_array($topic['id'], $class['covered_ids'], true),
                                    'bg-gray-200 dark:bg-white/10' => ! in_array($topic['id'], $class['covered_ids'], true),
                                ])
                            ></span>
                        @endforeach
                    </div>
                @endif

                <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-600 dark:text-gray-400">
                    @if (filled($class['teachers']))
                        <span>مدرس: {{ $class['teachers'] }}</span>
                    @endif
                    <span>جلسه‌های برنامه: {{ $class['sessions'] }} (برگزارشده {{ $class['held'] }})</span>
                    @if ($class['sessions'] < $totals['sessions'])
                        <span class="text-amber-700 dark:text-amber-400">طرح {{ $totals['sessions'] }} جلسه می‌خواهد؛ {{ $totals['sessions'] - $class['sessions'] }} جلسه کم است.</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-gray-500 dark:text-gray-400">هنوز برنامه‌ی هیچ کلاسی از این طرح پیروی نمی‌کند.</p>
        @endforelse
    </section>
</div>
