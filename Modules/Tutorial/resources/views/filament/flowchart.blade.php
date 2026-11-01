{{-- «نمای فلوچارت»: the scenario as swimlanes, laid out by TutorialFlowchart. --}}
@php
    $laneCount = max(count($chart['lanes']), 1);
    $laneWidth = 100 / $laneCount;
    $laneColors = [
        'bg-primary-500/10 text-primary-700 dark:text-primary-300',
        'bg-warning-500/10 text-warning-700 dark:text-warning-300',
        'bg-success-500/10 text-success-700 dark:text-success-300',
        'bg-info-500/10 text-info-700 dark:text-info-300',
        'bg-danger-500/10 text-danger-700 dark:text-danger-300',
    ];
@endphp

<div class="flex flex-col gap-3">
    <p class="text-sm text-gray-600 dark:text-gray-300">
        هر ستون یک نقش است و گام‌ها از بالا به پایین به ترتیب انجام می‌شوند. هر جا فلش به ستون دیگری می‌رود، کار به دست نقش دیگری می‌افتد.
    </p>

    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
        @if ($hasRun)
            <span class="flex items-center gap-1"><x-filament::icon icon="heroicon-s-check-circle" class="h-4 w-4 text-success-500" /> انجام شده</span>
            <span class="flex items-center gap-1"><x-filament::icon icon="heroicon-o-arrow-left-circle" class="h-4 w-4 text-primary-500" /> گام فعلی</span>
            <span class="flex items-center gap-1"><x-filament::icon icon="heroicon-o-lock-closed" class="h-4 w-4 text-gray-400" /> هنوز باز نشده</span>
        @endif
        <span class="flex items-center gap-1"><x-filament::icon icon="heroicon-o-arrows-right-left" class="h-4 w-4 text-warning-500" /> با حساب دیگری انجام می‌شود</span>
    </div>

    <div class="overflow-x-auto">
        <div class="relative min-w-[720px]" style="height: {{ $chart['height'] }}px">
            {{-- Lanes --}}
            @foreach ($chart['lanes'] as $index => $lane)
                <div @class([
                    'absolute inset-y-0 border-gray-200 dark:border-white/10',
                    'border-s' => $index > 0,
                    'bg-gray-50 dark:bg-white/[0.02]' => $index % 2 === 0,
                ]) style="right: {{ $index * $laneWidth }}%; width: {{ $laneWidth }}%">
                    <div @class(['m-2 rounded-lg px-2 py-1.5 text-center text-xs font-semibold', $laneColors[$index % count($laneColors)]])>
                        {{ $lane }}
                    </div>
                </div>
            @endforeach

            {{-- Chapter bands --}}
            @foreach ($chart['chapters'] as $chapter)
                <div class="absolute inset-x-0 flex items-center border-y border-dashed border-gray-300 bg-white/70 px-3 dark:border-white/15 dark:bg-gray-900/70"
                     style="top: {{ $chapter['top'] }}px; height: {{ \Modules\Tutorial\Services\TutorialFlowchart::CHAPTER_HEIGHT }}px">
                    <span class="text-sm font-bold text-gray-700 dark:text-gray-200">{{ $chapter['title'] }}</span>
                </div>
            @endforeach

            {{-- Connectors --}}
            <svg class="pointer-events-none absolute inset-0 h-full w-full text-gray-400 dark:text-gray-500" aria-hidden="true">
                <defs>
                    <marker id="tutorial-flow-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                        <path d="M 0 0 L 10 5 L 0 10 z" fill="currentColor" />
                    </marker>
                </defs>
                @foreach ($chart['edges'] as $edge)
                    @if ($edge['from']['x'] === $edge['to']['x'])
                        <line x1="{{ $edge['from']['x'] }}%" y1="{{ $edge['from']['y'] }}" x2="{{ $edge['to']['x'] }}%" y2="{{ $edge['to']['y'] - 1 }}" stroke="currentColor" stroke-width="1.5" marker-end="url(#tutorial-flow-arrow)" />
                    @else
                        <line x1="{{ $edge['from']['x'] }}%" y1="{{ $edge['from']['y'] }}" x2="{{ $edge['from']['x'] }}%" y2="{{ $edge['middle'] }}" stroke="currentColor" stroke-width="1.5" />
                        <line x1="{{ $edge['from']['x'] }}%" y1="{{ $edge['middle'] }}" x2="{{ $edge['to']['x'] }}%" y2="{{ $edge['middle'] }}" stroke="currentColor" stroke-width="1.5" stroke-dasharray="4 3" />
                        <line x1="{{ $edge['to']['x'] }}%" y1="{{ $edge['middle'] }}" x2="{{ $edge['to']['x'] }}%" y2="{{ $edge['to']['y'] - 1 }}" stroke="currentColor" stroke-width="1.5" marker-end="url(#tutorial-flow-arrow)" />
                    @endif
                @endforeach
            </svg>

            {{-- Missions --}}
            @foreach ($chart['nodes'] as $node)
                <div @class([
                    'absolute flex items-center gap-2 rounded-lg border px-2.5 shadow-sm',
                    'border-gray-300 bg-white dark:border-white/15 dark:bg-gray-800' => in_array($node['state'], ['idle', 'locked'], true),
                    'opacity-70' => $node['state'] === 'locked',
                    'border-success-300 bg-success-50 dark:border-success-500/40 dark:bg-success-950' => $node['state'] === 'done',
                    'border-primary-400 bg-primary-50 ring-2 ring-primary-400/50 dark:border-primary-500 dark:bg-primary-950' => $node['state'] === 'current',
                ])
                     style="right: calc({{ $node['lane'] * $laneWidth }}% + 10px); width: calc({{ $laneWidth }}% - 20px); top: {{ $node['top'] }}px; height: {{ \Modules\Tutorial\Services\TutorialFlowchart::NODE_HEIGHT }}px"
                     title="{{ $node['number'] }}. {{ $node['title'] }} — {{ $node['actor'] }}">
                    <span @class([
                        'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                        'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => in_array($node['state'], ['idle', 'locked'], true),
                        'bg-success-500 text-white' => $node['state'] === 'done',
                        'bg-primary-500 text-white' => $node['state'] === 'current',
                    ])>{{ $node['number'] }}</span>
                    <span class="line-clamp-2 flex-1 text-xs font-medium leading-5 text-gray-900 dark:text-white">{{ $node['title'] }}</span>
                    @if ($node['state'] === 'done')
                        <x-filament::icon icon="heroicon-s-check-circle" class="h-4 w-4 shrink-0 text-success-500" />
                    @elseif ($node['state'] === 'current')
                        <x-filament::icon icon="heroicon-o-arrow-left-circle" class="h-4 w-4 shrink-0 text-primary-500" />
                    @elseif ($node['state'] === 'locked')
                        <x-filament::icon icon="heroicon-o-lock-closed" class="h-4 w-4 shrink-0 text-gray-400" />
                    @endif
                    @if ($node['otherAccount'])
                        <x-filament::icon icon="heroicon-o-arrows-right-left" class="h-4 w-4 shrink-0 text-warning-500" />
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</div>
