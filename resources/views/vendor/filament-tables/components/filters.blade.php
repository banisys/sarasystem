@props([
    'applyAction',
    'form',
    'headingTag' => 'h3',
    'resetAction' => null,
    'resetActionPosition' => null,
])

@php
    use Filament\Tables\Enums\FiltersLayout;
    use Filament\Tables\Enums\FiltersResetActionPosition;

    $resetActionPosition ??= FiltersResetActionPosition::Header;
    $inSidebar = filament()->getCurrentPanel()?->getId() === 'admin'
        && $applyAction->getTable()?->getFiltersLayout() === FiltersLayout::BeforeContentCollapsible;
@endphp

@if ($inSidebar)
    @teleport('#sara-sidebar-filter-panels')
        <section
            class="sara-sidebar-filters"
            aria-label="{{ __('filament-tables::table.filters.heading') }}"
            x-cloak
            x-show="areFiltersOpen"
            x-bind:data-open="areFiltersOpen"
            x-data="{
                closeFilters() {
                    areFiltersOpen = false
                    if ($store.sidebar.isOpenMobile) {
                        $store.sidebar.close()
                    } else {
                        $refs.filtersTriggerActionContainer?.querySelector('button')?.focus()
                    }
                },
            }"
            x-init="$watch('areFiltersOpen', open => { if (open) $nextTick(() => $refs.closeSidebarFilters.focus()) })"
            x-effect="if (!$store.sidebar.isOpen) areFiltersOpen = false"
            x-on:sara-close-table-filters.window="areFiltersOpen = false"
            x-on:livewire:navigate.document="areFiltersOpen = false"
            x-on:keydown.escape.prevent.stop="closeFilters()"
        >
@endif

<div {{ $attributes->class(['fi-ta-filters', 'sara-sidebar-filter-form' => $inSidebar]) }}>
    <div class="fi-ta-filters-header">
        <{{ $headingTag }} class="fi-ta-filters-heading">
            {{ __('filament-tables::table.filters.heading') }}
        </{{ $headingTag }}>

        @if (($resetActionPosition === FiltersResetActionPosition::Header) && $resetAction?->isVisible())
            <div>
                {{ $resetAction->defaultView($resetAction::LINK_VIEW) }}
            </div>
        @endif

        @if ($inSidebar)
            <x-filament::icon-button
                icon="heroicon-o-x-mark"
                color="gray"
                :label="__('filament::components/modal.actions.close.label')"
                x-ref="closeSidebarFilters"
                x-on:click="closeFilters()"
            />
        @endif
    </div>

    @if ($inSidebar)
        <div class="sara-sidebar-filter-fields">{{ $form }}</div>
    @else
        {{ $form }}
    @endif

    @if ($applyAction->isVisible() || (($resetActionPosition === FiltersResetActionPosition::Footer) && $resetAction?->isVisible()))
        <div class="fi-ta-filters-actions-ctn">
            @if ($applyAction->isVisible())
                {{ $applyAction }}
            @endif

            @if (($resetActionPosition === FiltersResetActionPosition::Footer) && $resetAction?->isVisible())
                {{ $resetAction }}
            @endif
        </div>
    @endif
</div>

@if ($inSidebar)
        </section>
    @endteleport
@endif
