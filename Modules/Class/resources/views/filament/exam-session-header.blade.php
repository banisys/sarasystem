<div class="flex flex-col gap-10">
    <x-filament-panels::header
        :breadcrumbs="filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : []"
        :heading="$this->getHeading()"
        :subheading="$this->getSubheading()"
    />

    <div class="flex flex-wrap items-start gap-2" role="group" aria-label="عملیات جلسهٔ امتحانی">
        @foreach ($this->getCachedHeaderActions() as $action)
            @if ($action->isVisible())
                {{ $action }}
            @endif
        @endforeach

        @if ($scene !== null)
            @include('class::filament.session-scene', ['scene' => $scene, 'title' => $this->getTitle(), 'isExam' => true])
        @endif
    </div>
</div>
