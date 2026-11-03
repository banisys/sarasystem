<x-filament-panels::page>
    <div class="system-map space-y-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <p class="text-sm text-gray-600 dark:text-gray-300">ساختار فعلی سازمان؛ برای دیدن صفحه‌های هر بخش، کارت آن را باز کنید.</p>
            <x-filament::button tag="a" :href="static::getUrl()" icon="heroicon-o-arrow-path" color="gray">بازسازی نقشه</x-filament::button>
        </div>

        <section aria-label="راه‌های ورود به سامانه" class="grid gap-4 md:grid-cols-3">
            @foreach ($surfaceLabels as $surface => $label)
                <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <h2 class="font-semibold text-gray-950 dark:text-white">{{ $label }}</h2>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ ['public' => 'بازدیدکننده و متقاضی', 'user' => config('sarasystem.learner_label').'، مدرس و ولی', 'admin' => 'ستاد، مدیر و کارکنان'][$surface] }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($modules as $module)
                            @if ($module['enabled'] && in_array($surface, $module['surfaces'], true))
                                <a href="#map-{{ $module['name'] }}" class="text-sm text-primary-600 underline decoration-primary-200 underline-offset-4 dark:text-primary-400">{{ $module['label'] }}</a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>

        <div class="system-map-trunk" aria-hidden="true"></div>
        <section class="rounded-2xl border border-primary-200 bg-primary-50 p-6 text-center dark:border-primary-500/30 dark:bg-primary-500/10">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ config('app.name') }} · هستهٔ مشترک</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">یک سازمان، چند {{ config('sarasystem.tenant_label') }}؛ کاربران مشترک و دسترسی‌های جداگانه</p>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">ورود و حساب کاربری · نقش‌ها و دسترسی‌ها · فرایند و کارتابل · فرم‌های سفارشی</p>
            <details class="mt-4 text-sm">
                <summary class="cursor-pointer text-primary-700 dark:text-primary-300">صفحه‌های هسته</summary>
                <div class="mt-4 flex flex-wrap justify-center gap-4">
                    @foreach ($coreScreens as $screen)
                        <a href="{{ $screen['url'] }}" class="text-primary-700 underline underline-offset-4 dark:text-primary-300">{{ $screen['label'] }}</a>
                    @endforeach
                </div>
            </details>
        </section>

        <div class="system-map-branches grid items-start gap-6 lg:grid-cols-3">
            @foreach ($groups as $group => $members)
                <section class="system-map-branch space-y-4" aria-label="{{ $groupLabels[$group] }}">
                    <h2 class="text-center font-semibold text-gray-900 dark:text-white">{{ $groupLabels[$group] }}</h2>
                    @foreach ($members as $module)
                        <details id="map-{{ $module['name'] }}" class="scroll-mt-24 rounded-2xl border border-gray-200 bg-white shadow-sm open:ring-2 open:ring-primary-300 dark:border-white/10 dark:bg-white/5 dark:open:ring-primary-600">
                            <summary class="cursor-pointer p-5">
                                <span class="inline-flex max-w-full flex-wrap items-center gap-2 align-middle">
                                    <span class="font-semibold text-gray-950 dark:text-white">{{ $module['label'] }}</span>
                                    <x-filament::badge :color="$module['enabled'] ? 'success' : 'gray'">{{ $module['enabled'] ? 'فعال' : 'غیرفعال' }}</x-filament::badge>
                                </span>
                                <span class="mt-3 block text-sm leading-7 text-gray-600 dark:text-gray-300">{{ $module['description'] }}</span>
                                @if ($module['requires'])
                                    <span class="mt-2 block text-xs text-gray-500 dark:text-gray-400">نیازمند: {{ implode('، ', array_map(fn ($name) => $modules[$name]['label'] ?? $name, $module['requires'])) }}</span>
                                @endif
                            </summary>
                            <div class="space-y-3 border-t border-gray-100 p-5 dark:border-white/10">
                                @if (! $module['enabled'])
                                    <p class="text-sm text-gray-500 dark:text-gray-400">این امکان در نصب فعلی غیرفعال است.</p>
                                @else
                                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">صفحه‌های مدیریتی در {{ config('sarasystem.tenant_label') }} انتخاب‌شده</h3>
                                    @forelse ($module['screens'] as $screen)
                                        <a href="{{ $screen['url'] }}" class="block text-sm text-primary-600 underline underline-offset-4 dark:text-primary-400">{{ $screen['label'] }}</a>
                                    @empty
                                        <p class="text-sm text-gray-500 dark:text-gray-400">صفحهٔ مدیریتی قابل دسترسی در این بخش وجود ندارد.</p>
                                    @endforelse
                                @endif
                            </div>
                        </details>
                    @endforeach
                </section>
            @endforeach
        </div>

        <section class="space-y-4" aria-labelledby="system-map-connections">
            <div>
                <h2 id="system-map-connections" class="text-lg font-semibold text-gray-950 dark:text-white">ارتباط بخش‌ها در کار روزمره</h2>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">پیکان‌ها ارتباط کاری را نشان می‌دهند؛ وابستگی فنی با «نیازمند» روی کارت‌ها مشخص شده است.</p>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                @forelse ($connections as $connection)
                    <div class="flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-sm dark:border-white/10">
                        <span class="font-medium text-gray-900 dark:text-white">{{ $connection['from'] }}</span>
                        <span class="flex flex-1 flex-col items-center gap-1 text-center text-xs text-gray-500 dark:text-gray-400">
                            {{ $connection['label'] }}
                            <span class="system-map-arrow w-full" aria-hidden="true"></span>
                        </span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $connection['to'] }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">ارتباط کاری برای امکانات فعال ثبت نشده است.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
