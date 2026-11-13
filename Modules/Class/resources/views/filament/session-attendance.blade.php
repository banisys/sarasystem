{{-- A session's recorded attendance: the share of each status and its count. --}}
@php
    $total = array_sum(array_column($rows, 'count'));
    $bars = ['success' => 'bg-success-500', 'warning' => 'bg-warning-500', 'danger' => 'bg-danger-500', 'gray' => 'bg-gray-400'];
@endphp

@if ($total === 0)
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $isExam ? 'هنوز حضور و غیاب این جلسه ثبت نشده است.' : 'ثبت نشده' }}</p>
@else
    <div @class(['flex flex-col gap-3', 'max-w-xl' => $isExam])>
        <div class="flex h-2 gap-0.5 overflow-hidden rounded-full" aria-hidden="true">
            @foreach ($rows as $row)
                <span class="{{ $bars[$row['color']] }}" style="width: {{ round($row['count'] / $total * 100, 2) }}%"></span>
            @endforeach
        </div>

        <dl class="flex flex-col gap-2 text-sm">
            @foreach ($rows as $row)
                <div class="flex items-center justify-between gap-2">
                    <dt class="inline-flex items-center gap-2 text-gray-600 dark:text-gray-300">
                        <span class="size-2.5 rounded-full {{ $bars[$row['color']] }}"></span>
                        {{ $row['label'] }}
                    </dt>
                    <dd class="font-semibold text-gray-950 dark:text-white">{{ $row['count'] }}</dd>
                </div>
            @endforeach
            <div class="flex items-center justify-between gap-2 border-t border-gray-200 pt-2 dark:border-white/10">
                <dt class="text-gray-600 dark:text-gray-300">ثبت‌شده</dt>
                <dd class="font-semibold text-gray-950 dark:text-white">{{ $total }} نفر</dd>
            </div>
        </dl>
    </div>
@endif
