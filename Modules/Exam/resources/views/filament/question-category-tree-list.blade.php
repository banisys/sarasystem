<ul
    data-parent-id="{{ $parentId }}"
    @class(['flex flex-col gap-6', 'pt-4 ps-4 sm:pt-6 sm:ps-6' => $parentId === null, 'ms-1 sm:ms-2' => $parentId !== null])
>
    @foreach ($tree->childrenOf($parentId, $visibleIds) as $category)
        <li
            wire:key="question-category-{{ $category->id }}"
            @class([
                'relative ps-7 sm:ps-10' => $parentId !== null,
                'before:pointer-events-none before:absolute before:inset-s-0 before:-top-6 before:bottom-0 before:border-s before:border-gray-400 dark:before:border-gray-600 last:before:bottom-auto last:before:h-10' => $parentId !== null,
                'after:pointer-events-none after:absolute after:inset-s-0 after:top-4 after:w-6 after:border-t after:border-gray-400 dark:after:border-gray-600 sm:after:w-9' => $parentId !== null,
            ])
        >
            <div class="group relative flex min-h-8 min-w-0 flex-wrap items-center gap-x-2">
                @canany(['update', 'delete'], $category)
                    <button
                        type="button"
                        class="min-w-0 cursor-pointer wrap-anywhere text-start leading-8 font-medium text-gray-950 after:absolute after:inset-0 after:rounded-md focus-visible:after:ring-2 focus-visible:after:ring-primary-500 dark:text-white"
                        wire:click="mountAction('edit', { category: {{ $category->id }} })"
                    >
                        {{ $category->title }}
                    </button>
                @else
                    <span class="min-w-0 wrap-anywhere leading-8 font-medium text-gray-950 dark:text-white">{{ $category->title }}</span>
                @endcanany

                @if ($canCreate)
                    <x-filament::icon-button
                        icon="heroicon-o-plus"
                        color="gray"
                        size="sm"
                        label="افزودن زیردسته"
                        tooltip="افزودن زیردسته"
                        class="relative z-10 [@media(hover:hover)]:pointer-events-none [@media(hover:hover)]:opacity-0 group-focus-within:pointer-events-auto group-focus-within:opacity-100 group-hover:pointer-events-auto group-hover:opacity-100"
                        wire:click="mountAction('create', { parent: {{ $category->id }} })"
                    />
                @endif

                <span class="text-xs leading-8 text-gray-500 dark:text-gray-400">{{ $category->questions_count }} سؤال</span>
            </div>

            @if ($tree->childrenOf($category->id, $visibleIds) !== [])
                <div class="mt-6">
                    @include('exam::filament.question-category-tree-list', ['parentId' => $category->id])
                </div>
            @endif
        </li>
    @endforeach
</ul>
