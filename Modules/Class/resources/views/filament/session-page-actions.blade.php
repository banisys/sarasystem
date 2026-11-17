<div class="flex flex-col gap-3" role="group" aria-label="{{ $isExam ? 'عملیات جلسهٔ امتحانی' : 'عملیات جلسهٔ درسی' }}">
    @foreach ($this->getCachedHeaderActions() as $action)
        @if ($action->isVisible())
            {{ $action }}
        @endif
    @endforeach

    @if ($scene !== null)
        @include('class::filament.session-scene', ['scene' => $scene, 'title' => $title])
    @endif
</div>
