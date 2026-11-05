{{-- Beside the modal's × button (ADR 0060). One button with two icons, so it keeps the focus when clicked. --}}
@php
    use Filament\Support\Enums\IconSize;
    use Filament\Support\Icons\Heroicon;
    use Filament\Support\View\ComponentAttributeBag;
    use Filament\Support\View\Components\IconButtonComponent;

    $enterIcon = \Filament\Support\generate_icon_html(Heroicon::OutlinedArrowsPointingOut, attributes: new ComponentAttributeBag(['x-show' => '! isFullscreen']), size: IconSize::Large);
    $exitIcon = \Filament\Support\generate_icon_html(Heroicon::OutlinedArrowsPointingIn, attributes: new ComponentAttributeBag(['x-cloak' => true, 'x-show' => 'isFullscreen']), size: IconSize::Large);
@endphp

<button
    type="button"
    x-on:click="isFullscreen = ! isFullscreen"
    x-bind:aria-label="isFullscreen ? 'خروج از تمام‌صفحه' : 'تمام‌صفحه'"
    x-bind:aria-pressed="isFullscreen"
    x-bind:title="isFullscreen ? 'خروج از تمام‌صفحه' : 'تمام‌صفحه'"
    @if ($closeByEscaping)
        tabindex="-1"
    @endif
    {{ (new ComponentAttributeBag)->color(IconButtonComponent::class, 'gray')->class(['fi-icon-btn', 'fi-size-md', 'fi-modal-fullscreen-btn']) }}
>
    {{ $enterIcon }}
    {{ $exitIcon }}
</button>
