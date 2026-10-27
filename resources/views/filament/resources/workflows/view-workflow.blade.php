<x-filament-panels::page>
    @php
        $workflow = $this->getRecord();
        $steps = $this->steps();
        $card = 'rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10';
    @endphp

    {{-- Summary --}}
    <div class="{{ $card }} flex flex-wrap items-center gap-x-6 gap-y-2 p-4 text-sm">
        <div>
            <span class="text-gray-500 dark:text-gray-400">برای:</span>
            <span class="font-semibold text-gray-950 dark:text-white">{{ $this->subjectLabel() }}</span>
        </div>
        <div>
            <span class="text-gray-500 dark:text-gray-400">تعداد مرحله‌ها:</span>
            <span class="font-semibold text-gray-950 dark:text-white">{{ count($steps) }}</span>
        </div>
        <div>
            <span class="text-gray-500 dark:text-gray-400">پرونده‌های در جریان:</span>
            <span class="font-semibold text-gray-950 dark:text-white">{{ collect($steps)->sum('openCases') }}</span>
        </div>
        <x-filament::badge :color="$workflow->is_active ? 'success' : 'gray'">
            {{ $workflow->is_active ? 'فعال' : 'غیرفعال' }}
        </x-filament::badge>
        @if (filled($workflow->description))
            <p class="w-full text-gray-600 dark:text-gray-300">{{ $workflow->description }}</p>
        @endif
    </div>

    {{-- Flowchart, top to bottom --}}
    <div class="mx-auto flex w-full max-w-4xl flex-col items-center">
        {{-- Start --}}
        <div class="flex items-center gap-2 rounded-full bg-primary-50 px-5 py-2 text-sm font-semibold text-primary-700 ring-1 ring-primary-600/20 dark:bg-primary-400/10 dark:text-primary-300 dark:ring-primary-400/30">
            <x-filament::icon icon="heroicon-o-play" class="h-5 w-5" />
            شروع: {{ $this->subjectLabel() }} ثبت می‌شود
        </div>

        @foreach ($steps as $step)
            {{-- Arrow into the step --}}
            <div class="flex flex-col items-center py-1 text-gray-400 dark:text-gray-500">
                <div class="h-5 w-px bg-gray-300 dark:bg-white/20"></div>
                <span class="text-xs">{{ $loop->first ? '' : 'تأیید' }}</span>
                <x-filament::icon icon="heroicon-m-chevron-down" class="h-5 w-5" />
            </div>

            <div class="grid w-full grid-cols-1 items-center gap-3 md:grid-cols-[1fr_auto]">
                {{-- Step card --}}
                <div class="{{ $card }} p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-600 text-sm font-bold text-white">
                            {{ $step['number'] }}
                        </div>
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $step['name'] }}</h3>
                                @if ($step['openCases'] > 0)
                                    <x-filament::badge color="info" icon="heroicon-m-inbox-stack">
                                        {{ $step['openCases'] }} پرونده در این مرحله
                                    </x-filament::badge>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                                <x-filament::icon :icon="$step['assigneeIsUser'] ? 'heroicon-o-user' : 'heroicon-o-user-group'" class="h-5 w-5 text-gray-400" />
                                <span>{{ $step['assignee'] }}</span>
                                @unless ($step['assigneeIsUser'])
                                    <span class="text-xs text-gray-400">(تأیید یکی کافی است)</span>
                                @endunless
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($step['actions'] as $action)
                                    <x-filament::badge :color="$action['color']" size="sm">{{ $action['label'] }}</x-filament::badge>
                                @endforeach
                            </div>
                            @foreach ($step['notes'] as $note)
                                <div class="flex items-start gap-1 text-xs text-gray-600 dark:text-gray-300">
                                    <x-filament::icon icon="heroicon-m-document-text" class="h-4 w-4 shrink-0 text-gray-400" />
                                    <span>{{ $note }}</span>
                                </div>
                            @endforeach
                            @if ($step['returns'])
                                <div class="flex items-center gap-1 text-xs text-warning-600 dark:text-warning-400">
                                    <x-filament::icon icon="heroicon-m-arrow-uturn-up" class="h-4 w-4" />
                                    {{ $step['number'] === 2 ? 'می‌تواند به مرحله‌ی قبل برگرداند' : 'می‌تواند به یکی از مرحله‌های 1 تا '.($step['number'] - 1).' برگرداند' }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Reject exit beside the step --}}
                <div class="flex items-center justify-center gap-2 md:w-40 md:justify-start">
                    @if ($step['rejects'])
                        <span class="hidden h-px w-6 bg-danger-300 md:block dark:bg-danger-400/40"></span>
                        <div class="flex items-center gap-1 rounded-full bg-danger-50 px-3 py-1 text-xs text-danger-700 ring-1 ring-danger-600/20 dark:bg-danger-400/10 dark:text-danger-300">
                            <x-filament::icon icon="heroicon-m-x-circle" class="h-4 w-4" />
                            رد ← پایان
                        </div>
                    @endif
                </div>
            </div>
        @endforeach

        {{-- End --}}
        <div class="flex flex-col items-center py-1 text-gray-400 dark:text-gray-500">
            <div class="h-5 w-px bg-gray-300 dark:bg-white/20"></div>
            <span class="text-xs">{{ count($steps) ? 'تأیید نهایی' : '' }}</span>
            <x-filament::icon icon="heroicon-m-chevron-down" class="h-5 w-5" />
        </div>
        <div class="flex max-w-xl flex-col items-center gap-1 rounded-2xl bg-success-50 px-5 py-3 text-center text-sm text-success-700 ring-1 ring-success-600/20 dark:bg-success-400/10 dark:text-success-300 dark:ring-success-400/30">
            <div class="flex items-center gap-2 font-semibold">
                <x-filament::icon icon="heroicon-o-check-badge" class="h-5 w-5" />
                پایان: تأیید نهایی
            </div>
            @if ($summary = $this->completionSummary())
                <p class="text-xs">{{ $summary }}</p>
            @endif
        </div>
    </div>
</x-filament-panels::page>
