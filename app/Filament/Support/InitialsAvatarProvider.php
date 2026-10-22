<?php

namespace App\Filament\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Avatar with the user's initials on a primary-color gradient, generated as
 * an inline SVG (no external avatar service). Ported from LMS.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        // Persian letters join, so the initials are kept apart by a ZWNJ.
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->squish()
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $segment): string => mb_substr($segment, 0, 1))
            ->join("\u{200C}");

        $primary = FilamentColor::getColor('primary') ?? Color::Violet;
        $from = Color::convertToHex($primary[400]);
        $to = Color::convertToHex($primary[700]);

        $content = filled($initials)
            ? '<text x="50" y="50" dy=".35em" text-anchor="middle" font-family="Tahoma, Shabnam, sans-serif" font-size="38" font-weight="700" fill="#fff">'.e($initials).'</text>'
            // No name: a person silhouette.
            : '<g fill="#fff" fill-opacity=".9"><circle cx="50" cy="38" r="16"/><path d="M20 82a30 26 0 0 1 60 0z"/></g>';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="'.$from.'"/><stop offset="1" stop-color="'.$to.'"/></linearGradient></defs>'
            .'<rect width="100" height="100" fill="url(#g)"/>'
            .$content
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
