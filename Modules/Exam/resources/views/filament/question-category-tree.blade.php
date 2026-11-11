<div class="flex flex-col gap-4">
    <div class="max-w-sm">
        <x-filament::input.wrapper>
            <x-filament::input
                type="search"
                wire:model.live.debounce.300ms="search"
                aria-label="جست‌وجوی دسته‌ها"
                placeholder="جست‌وجوی دسته‌ها"
            />
        </x-filament::input.wrapper>
    </div>

    @if ($tree->childrenOf(null, $visibleIds) !== [])
        @include('exam::filament.question-category-tree-list', ['parentId' => null])
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            @if ($visibleIds !== null)
                دسته‌ای مطابق جست‌وجو پیدا نشد.
            @elseif ($canCreate)
                هنوز دسته‌ای وجود ندارد. با «افزودن دسته» شروع کنید.
            @else
                هنوز دسته‌ای وجود ندارد.
            @endif
        </p>
    @endif
</div>
