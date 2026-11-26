@foreach ($actions as $action)
    @if ($action->isVisible())
        @teleport('#course-participant-actions')
            <div>{{ $action }}</div>
        @endteleport
    @endif
@endforeach
