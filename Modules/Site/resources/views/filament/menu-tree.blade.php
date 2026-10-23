{{-- A menu's item tree (MenuItems page, ADR 0012). Lists are SortableJS lists in one group, so items can move between levels. --}}
<div class="fi-section rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    @include('site::filament.menu-tree-list', ['items' => $items, 'parentId' => null, 'canEdit' => $canEdit])

    @if ($items->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">هنوز آیتمی ندارد؛ با «افزودن آیتم» شروع کنید.</p>
    @elseif ($canEdit)
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">برای جابه‌جایی، آیتم را از دستگیره‌اش بکشید؛ می‌توانید آن را زیر آیتم دیگری هم ببرید.</p>
    @endif
</div>
