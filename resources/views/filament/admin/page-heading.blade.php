{{ $title }}
@if (filled($context))
    <span class="text-[0.7em] font-normal text-gray-600 dark:text-gray-300">(<bdi>{{ $context }}</bdi>)</span>
@endif
