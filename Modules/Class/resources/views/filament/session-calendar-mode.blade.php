{{-- The class's mode as icons (ADR 0047): a building for in person, a camera for online, both for blended. --}}
@if ($mode !== 'online')
    <x-filament::icon icon="heroicon-m-building-office-2" class="size-3.5 shrink-0" title="حضوری" />
@endif
@if ($mode !== 'in_person')
    <x-filament::icon icon="heroicon-m-video-camera" class="size-3.5 shrink-0" title="آنلاین" />
@endif
