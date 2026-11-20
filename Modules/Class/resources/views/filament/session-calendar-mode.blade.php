@if ($mode === 'online')
    <x-filament::icon icon="heroicon-m-video-camera" class="size-3.5 shrink-0" title="آنلاین" />
@else
    <x-filament::icon icon="heroicon-m-building-office-2" class="size-3.5 shrink-0" title="حضوری" />
@endif
