<x-filament-panels::page>
    @if ($run === null)
        <p class="text-sm text-gray-600 dark:text-gray-300">
            سناریویی را که به کار شما نزدیک‌تر است انتخاب کنید. در حالت آموزش هر گام را خودتان در همین سامانه انجام می‌دهید؛
            هر وقت گامی انجام شد تیک می‌خورد و گام بعدی باز می‌شود. هر چه در آموزش ساخته شود فقط برای شما دیده می‌شود و با «شروع دوباره» پاک می‌شود.
        </p>

        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($scenarios as $item)
                <x-filament::section :heading="$item->title()" :description="count($item->missions()).' گام در '.count($item->chapters()).' بخش'">
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $item->description() }}</p>
                    <x-slot name="footer">
                        <x-filament::button wire:click="start('{{ $item->key() }}')" icon="heroicon-o-play">شروع</x-filament::button>
                    </x-slot>
                </x-filament::section>
            @endforeach
        </div>
    @else
        @php($done = count($run->completed_missions ?? []))
        @php($total = count($scenario->missions()))

        <x-filament::section>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="min-w-48 flex-1">
                <div class="text-lg font-semibold text-gray-950 dark:text-white">{{ $scenario->title() }}</div>
                <div class="mt-2 h-2 rounded-full bg-gray-100 dark:bg-white/10">
                    <div class="h-2 rounded-full bg-primary-500" style="width: {{ $total ? round($done * 100 / $total) : 0 }}%"></div>
                </div>
                <div class="mt-1 text-xs text-gray-500">{{ $done }} از {{ $total }} گام</div>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($current !== null)
                    @if ($isActive)
                        <x-filament::button wire:click="pause" color="gray" icon="heroicon-o-pause">توقف حالت آموزش</x-filament::button>
                    @else
                        <x-filament::button wire:click="resume" icon="heroicon-o-play">ادامه در حالت آموزش</x-filament::button>
                    @endif
                @endif
                {{ $this->resetAction }}
            </div>
        </div>
        </x-filament::section>

        @if ($current === null)
            <x-filament::section icon="heroicon-o-check-badge" icon-color="success" heading="همه‌ی گام‌ها انجام شد.">
                <p class="text-sm text-gray-600 dark:text-gray-300">برای تمرین دوباره «شروع دوباره» را بزنید؛ داده‌های آموزشی پاک می‌شوند.</p>
            </x-filament::section>
        @elseif (! $isActive)
            <p class="text-sm text-warning-600 dark:text-warning-400">
                حالت آموزش خاموش است: آنچه الان بسازید جزو آموزش حساب نمی‌شود و گام‌ها تیک نمی‌خورند. برای ادامه «ادامه در حالت آموزش» را بزنید.
            </p>
        @endif

        @php($number = 0)
        @foreach ($scenario->chapters() as $chapter => $missions)
            <x-filament::section :heading="$chapter">
                <ol class="flex flex-col gap-2">
                    @foreach ($missions as $mission)
                        @php($number++)
                        @php($isDone = $run->isCompleted($mission->key))
                        @php($isCurrent = $current?->key === $mission->key)
                        <li @class([
                            'rounded-lg p-3',
                            'bg-primary-50 ring-1 ring-primary-200 dark:bg-primary-500/10 dark:ring-primary-500/30' => $isCurrent,
                            'opacity-60' => ! $isDone && ! $isCurrent,
                        ])>
                            <div class="flex items-center gap-2">
                                @if ($isDone)
                                    <x-filament::icon icon="heroicon-s-check-circle" class="h-5 w-5 text-success-500" />
                                @elseif ($isCurrent)
                                    <x-filament::icon icon="heroicon-o-arrow-left-circle" class="h-5 w-5 text-primary-500" />
                                @else
                                    <x-filament::icon icon="heroicon-o-lock-closed" class="h-5 w-5 text-gray-400" />
                                @endif
                                <span class="font-medium text-gray-950 dark:text-white">{{ $number }}. {{ $mission->title }}</span>
                                <span class="text-xs text-gray-500">({{ $mission->actorLabel() }})</span>
                            </div>

                            @if ($isCurrent)
                                <ul class="mt-2 list-disc ps-10 text-sm text-gray-700 dark:text-gray-200">
                                    @foreach ($mission->stepList() as $step)
                                        <li>{{ $step }}</li>
                                    @endforeach
                                </ul>
                                @if ($isActive)
                                    <div class="mt-3 flex flex-wrap gap-2 ps-7">
                                        @if ($url = $mission->urlFor($run))
                                            <x-filament::button tag="a" :href="$url" size="sm" icon="heroicon-o-arrow-top-right-on-square">{{ $mission->urlLabel() }}</x-filament::button>
                                        @endif
                                        @if (($account = $mission->actingAs($run)) && auth()->id() !== $account->getKey())
                                            <form method="POST" action="{{ route('tutorial.act-as') }}">
                                                @csrf
                                                <x-filament::button type="submit" size="sm" color="warning" icon="heroicon-o-arrows-right-left">ورود با حساب {{ $account->name }}</x-filament::button>
                                            </form>
                                        @endif
                                    </div>
                                @endif
                            @endif
                        </li>
                    @endforeach
                </ol>
            </x-filament::section>
        @endforeach
    @endif
</x-filament-panels::page>
