@foreach ($actions as $action)
    @if ($action->isVisible())
        @teleport('#course-page-actions')
            <div>{{ $action }}</div>
        @endteleport
    @endif
@endforeach
