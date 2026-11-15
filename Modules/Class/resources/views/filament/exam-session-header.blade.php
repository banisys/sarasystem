<div class="flex flex-col gap-10">
    <x-filament-panels::header
        :breadcrumbs="filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : []"
        :heading="$this->getHeading()"
        :subheading="$this->getSubheading()"
    />

    @php
        [$actionGroups, $actions] = collect($this->getCachedHeaderActions())
            ->filter(fn ($action) => $action->isVisible())
            ->partition(fn ($action) => $action instanceof \Filament\Actions\ActionGroup);
    @endphp

    <div class="flex flex-wrap items-start gap-2" role="group" aria-label="عملیات جلسهٔ امتحانی">
        @foreach ($actions as $action)
            {{ $action }}
        @endforeach

        @if ($scene !== null)
            @include('class::filament.session-scene', ['scene' => $scene, 'title' => $this->getTitle(), 'isExam' => true])
        @endif

        @foreach ($actionGroups as $actionGroup)
            {{ $actionGroup }}
        @endforeach
    </div>
</div>
