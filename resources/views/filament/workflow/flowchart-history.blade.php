{{-- What was done in one step of a case: who, when, notes, changes (WorkflowFlowchart::ofCase). --}}
<ol class="space-y-2">
    @foreach ($entries as $entry)
        <li class="rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-white/5">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <x-filament::badge :color="$entry['color']" size="sm">{{ $entry['label'] }}</x-filament::badge>
                <span class="font-medium text-gray-950 dark:text-white">{{ $entry['user'] }}</span>
                @if ($entry['target'])
                    <span class="text-warning-600 dark:text-warning-400">{{ $entry['target'] }}</span>
                @endif
                <span class="ms-auto text-gray-500 dark:text-gray-400" dir="ltr">{{ $entry['at'] }}</span>
            </div>
            @if (filled($entry['comment']))
                <p class="mt-1 whitespace-pre-line text-gray-700 dark:text-gray-200">{{ $entry['comment'] }}</p>
            @endif
            @if (filled($entry['changes']))
                <ul class="mt-1 list-inside list-disc text-gray-600 dark:text-gray-300">
                    @foreach ($entry['changes'] as $change)
                        <li>{{ $change }}</li>
                    @endforeach
                </ul>
            @endif
        </li>
    @endforeach
</ol>
