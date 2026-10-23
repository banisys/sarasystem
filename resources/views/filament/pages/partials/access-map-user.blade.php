{{-- One user in an access-map tenant card; the tooltip lists all their memberships. --}}
@php
    $user = $users[$userId] ?? null;
    $url = $user ? $this->userUrl($userId, $tenant) : null;
@endphp
@if ($user)
    <li class="flex min-w-0 items-center gap-1.5 text-sm" title="{{ $membershipTitle($userId) }}">
        <a
            @if ($url) href="{{ $url }}" wire:navigate @endif
            class="truncate text-gray-700 hover:text-primary-600 hover:underline dark:text-gray-300 dark:hover:text-primary-400"
        >{{ $user->name }}</a>
        @if (isset($staff[$tenant->id][$userId]))
            <x-filament::icon icon="heroicon-m-computer-desktop" class="h-3.5 w-3.5 shrink-0 text-gray-400 dark:text-gray-500" />
        @endif
        @if (count($byUser[$userId] ?? []) > 1)
            <x-filament::icon icon="heroicon-m-arrows-right-left" class="h-3.5 w-3.5 shrink-0 text-amber-500" />
        @endif
    </li>
@endif
