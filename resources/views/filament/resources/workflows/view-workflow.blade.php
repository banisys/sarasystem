<x-filament-panels::page>
    @php
        $workflow = $this->getRecord();
        $chart = $this->chart();
        $steps = $chart['steps'];
        $card = 'rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10';
    @endphp

    {{-- Summary --}}
    <div class="{{ $card }} flex flex-wrap items-center gap-x-6 gap-y-2 p-4 text-sm">
        <div>
            <span class="text-gray-500 dark:text-gray-400">برای:</span>
            <span class="font-semibold text-gray-950 dark:text-white">{{ $chart['subjectLabel'] }}</span>
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

    @include('filament.workflow.flowchart', ['chart' => $chart])
</x-filament-panels::page>
