{{-- A widget's label in the page builder (Widget::itemLabel()): title first, then the type, smaller and lighter. --}}
<bdi>{{ $title }}</bdi>
<span class="text-xs font-normal text-gray-400 dark:text-gray-500">(<bdi>{{ $type }}</bdi>)</span>
@unless ($isVisible)
    <span class="text-xs font-normal text-warning-600 dark:text-warning-400">— مخفی</span>
@endunless
