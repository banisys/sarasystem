{{-- The controls of a Campus 3D page; the page's Alpine data has `scene`, `autoRotate` and `names`. --}}
@php
    $toggleOn = 'ring-2 ring-primary-500 bg-primary-50! text-primary-700! dark:bg-primary-500/20! dark:text-primary-200!';
@endphp
<div class="flex flex-wrap items-center gap-x-4 gap-y-2">
    <div class="flex flex-wrap items-center gap-1" role="group" aria-label="نماهای آماده">
        <x-filament::button x-on:click="scene.view('corner')" color="gray" size="xs">سه‌رخ</x-filament::button>
        <x-filament::button x-on:click="scene.view('front')" color="gray" size="xs">از روبه‌رو</x-filament::button>
        <x-filament::button x-on:click="scene.view('side')" color="gray" size="xs">از کنار</x-filament::button>
        <x-filament::button x-on:click="scene.view('top')" color="gray" size="xs">از بالا</x-filament::button>
    </div>
    <div class="flex items-center gap-1" role="group" aria-label="زوم">
        <x-filament::icon-button x-on:click="scene.zoom(0.75)" icon="heroicon-m-magnifying-glass-plus" color="gray" label="نزدیک‌تر" />
        <x-filament::icon-button x-on:click="scene.zoom(1.33)" icon="heroicon-m-magnifying-glass-minus" color="gray" label="دورتر" />
    </div>
    <div class="flex flex-wrap items-center gap-1" role="group" aria-label="گزینه‌ها">
        <x-filament::button x-on:click="autoRotate = ! autoRotate; scene.setAutoRotate(autoRotate)" x-bind:aria-pressed="autoRotate" x-bind:class="autoRotate && '{{ $toggleOn }}'" color="gray" size="xs" icon="heroicon-m-arrow-path">چرخش خودکار</x-filament::button>
        <x-filament::button x-on:click="names = ! names; scene.setNames(names)" x-bind:aria-pressed="names" x-bind:class="names && '{{ $toggleOn }}'" color="gray" size="xs" icon="heroicon-m-tag">نام همه</x-filament::button>
    </div>
</div>
