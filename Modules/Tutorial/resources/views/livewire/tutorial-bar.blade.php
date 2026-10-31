{{-- The step-by-step tutorial's bar (ADR 0040); empty outside tutorial mode. --}}
<div @if ($guide) wire:poll.5s @endif>
    @if ($guide)
        <div x-data="{ open: true }" class="border-b border-primary-200 bg-primary-50 px-4 py-2 text-sm text-primary-950 dark:border-primary-500/30 dark:bg-primary-950 dark:text-primary-50">
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <span class="font-semibold">آموزش «{{ $guide['scenario'] }}» · {{ min($guide['done'] + 1, $guide['total']) }} از {{ $guide['total'] }}</span>

                @if ($guide['finished'])
                    <span>همه‌ی گام‌ها انجام شد.</span>
                @else
                    <span>{{ $guide['mission']['title'] }} <span class="text-xs opacity-75">({{ $guide['mission']['actor'] }})</span></span>
                    <button type="button" class="text-xs underline" x-on:click="open = ! open" x-text="open ? 'بستن' : 'جزئیات'"></button>
                @endif

                @if ($justDone)
                    <span class="rounded-md bg-success-500 px-2 py-0.5 text-xs text-white">{{ $justDone }}</span>
                @endif

                <div class="ms-auto flex flex-wrap items-center gap-2">
                    @if (! $guide['finished'] && $guide['mission']['url'])
                        <a href="{{ $guide['mission']['url'] }}" class="rounded-md bg-primary-600 px-3 py-1 text-xs font-medium text-white hover:bg-primary-700">{{ $guide['mission']['urlLabel'] }}</a>
                    @endif
                    @if ($guide['actAs'])
                        <form method="POST" action="{{ $guide['actAs']['url'] }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600">ورود با حساب {{ $guide['actAs']['name'] }}</button>
                        </form>
                    @endif
                    @if ($guide['ownAccountUrl'])
                        <form method="POST" action="{{ $guide['ownAccountUrl'] }}">
                            @csrf
                            <button type="submit" class="rounded-md bg-amber-500 px-3 py-1 text-xs font-medium text-white hover:bg-amber-600">بازگشت به حساب خودم</button>
                        </form>
                    @endif
                    @unless (\App\Auth\Impersonation::isActive())
                        <a href="{{ $guide['pageUrl'] }}" class="text-xs underline">همه‌ی گام‌ها</a>
                    @endunless
                </div>
            </div>

            @if (! $guide['finished'])
                <ul x-show="open" class="mt-2 list-disc ps-6 text-xs leading-6">
                    @foreach ($guide['mission']['steps'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
