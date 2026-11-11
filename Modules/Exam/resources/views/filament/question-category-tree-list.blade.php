<ul
    data-parent-id="{{ $parentId }}"
    @class(['flex flex-col gap-6', 'ms-1 sm:ms-2' => $parentId !== null])
>
    @foreach ($tree->childrenOf($parentId, $visibleIds) as $category)
        <li
            wire:key="question-category-{{ $category->id }}"
            @class([
                'relative ps-5 sm:ps-6' => $parentId !== null,
                'before:pointer-events-none before:absolute before:inset-s-0 before:-top-6 before:bottom-0 before:border-s before:border-gray-400 dark:before:border-gray-600 last:before:bottom-auto last:before:h-10' => $parentId !== null,
                'after:pointer-events-none after:absolute after:inset-s-0 after:top-4 after:w-4 after:border-t after:border-gray-400 dark:after:border-gray-600 sm:after:w-5' => $parentId !== null,
            ])
        >
            <div class="flex min-h-8 flex-wrap items-start justify-between gap-x-4 gap-y-1">
                <div class="flex min-w-0 flex-wrap items-start gap-x-2">
                    <span class="min-w-0 wrap-anywhere leading-8 font-medium text-gray-950 dark:text-white">{{ $category->title }}</span>
                    <span class="text-xs leading-8 text-gray-500 dark:text-gray-400">{{ $category->questions_count }} سؤال</span>
                </div>

                <div class="flex min-h-8 items-center gap-1">
                    @if ($canCreate)
                        <x-filament::icon-button icon="heroicon-o-plus" color="gray" size="sm" label="افزودن زیردسته" tooltip="افزودن زیردسته"
                            wire:click="mountAction('create', { parent: {{ $category->id }} })" />
                    @endif
                    @can('update', $category)
                        <x-filament::icon-button icon="heroicon-o-pencil-square" color="gray" size="sm" label="ویرایش دسته" tooltip="ویرایش دسته"
                            wire:click="mountAction('edit', { category: {{ $category->id }} })" />
                    @endcan
                    @can('delete', $category)
                        <x-filament::icon-button icon="heroicon-o-trash" color="danger" size="sm" label="حذف دسته" tooltip="حذف دسته"
                            wire:click="mountAction('delete', { category: {{ $category->id }} })" />
                    @endcan
                </div>
            </div>

            @if ($tree->childrenOf($category->id, $visibleIds) !== [])
                <div class="mt-6">
                    @include('exam::filament.question-category-tree-list', ['parentId' => $category->id])
                </div>
            @endif
        </li>
    @endforeach
</ul>
