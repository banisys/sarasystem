<ul
    @if ($canEdit)
        x-sortable
        x-sortable-group="menu-items"
        data-sortable-animation-duration="150"
        {{-- "end" fires on the list the item left; $event.to is the list it went into. --}}
        x-on:end.stop="$wire.moveItem($event.item.getAttribute('x-sortable-item'), $event.to.dataset.parentId || null, $event.to.sortable.toArray())"
    @endif
    data-parent-id="{{ $parentId }}"
    @class(['space-y-2', 'min-h-4' => $canEdit, 'border-s-2 border-gray-100 ps-4 dark:border-white/5' => $parentId !== null])
>
    @foreach ($items as $item)
        <li x-sortable-item="{{ $item->id }}" wire:key="menu-item-{{ $item->id }}">
            <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 dark:border-white/10">
                <div class="flex min-w-0 items-center gap-2">
                    @if ($canEdit)
                        <span x-sortable-handle class="cursor-move text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <x-filament::icon icon="heroicon-o-bars-3" class="h-4 w-4" />
                        </span>
                    @endif

                    <span @class(['font-medium', 'text-gray-950 dark:text-white' => $item->is_visible, 'text-gray-400 line-through dark:text-gray-500' => ! $item->is_visible])>{{ $item->title }}</span>
                    <span class="truncate text-xs text-gray-500 dark:text-gray-400"><bdi>{{ $item->targetLabel() }}</bdi></span>

                    @unless ($item->is_visible)
                        <x-filament::badge color="gray" size="sm">مخفی</x-filament::badge>
                    @endunless
                    @if ($item->link_type === \Modules\Site\Models\MenuItem::LINK_PAGE && $item->href() === null)
                        <x-filament::badge color="warning" size="sm">در سایت نمایش داده نمی‌شود</x-filament::badge>
                    @endif
                </div>

                @if ($canEdit)
                    <div class="flex items-center gap-1">
                        <x-filament::icon-button icon="heroicon-o-plus" color="gray" size="sm" label="افزودن زیرمنو" tooltip="افزودن زیرمنو"
                            wire:click="mountAction('addItem', { parent: {{ $item->id }} })" />
                        <x-filament::icon-button icon="heroicon-o-pencil-square" color="gray" size="sm" label="ویرایش" tooltip="ویرایش"
                            wire:click="mountAction('editItem', { item: {{ $item->id }} })" />
                        <x-filament::icon-button icon="heroicon-o-trash" color="danger" size="sm" label="حذف" tooltip="حذف"
                            wire:click="mountAction('deleteItem', { item: {{ $item->id }} })" />
                    </div>
                @endif
            </div>

            @if ($canEdit || $item->children->isNotEmpty())
                <div class="mt-2">
                    @include('site::filament.menu-tree-list', ['items' => $item->children, 'parentId' => $item->id, 'canEdit' => $canEdit])
                </div>
            @endif
        </li>
    @endforeach
</ul>
