<span>
    @isset($label)
        {{ $label }}:
    @endisset
    @foreach ($paths as $path)
        @unless ($loop->first)
            ،
        @endunless
        <span>
            @foreach ($path as $segment)
                @unless ($loop->first)
                    <x-hierarchy-path-separator />
                @endunless
                <span>{{ $segment }}</span>
            @endforeach
        </span>
    @endforeach
</span>
