<ul
    data-parent-id="{{ $parentId }}"
    @class(['space-y-2', 'border-s-2 border-gray-100 ps-4 dark:border-white/5' => $parentId !== null])
>
    @foreach ($tree->childrenOf($parentId, $visibleIds) as $category)
        <li wire:key="question-category-{{ $category->id }}">
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10">
                <div class="flex min-w-0 flex-wrap items-center gap-2">
                    <x-filament::icon icon="heroicon-o-folder" class="h-4 w-4 text-gray-400" />
                    <span class="min-w-0 break-words font-medium text-gray-950 dark:text-white">{{ $category->title }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $category->questions_count }} سؤال</span>
                </div>

                <div class="flex items-center gap-1">
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
                <div class="mt-2">
                    @include('exam::filament.question-category-tree-list', ['parentId' => $category->id])
                </div>
            @endif
        </li>
    @endforeach
</ul>
