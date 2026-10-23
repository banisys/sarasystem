<?php

namespace Modules\Site\Widgets;

use Filament\Forms\Components\Builder\Block;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;

/**
 * One kind of building block for a page's content (ADR 0011). A page stores
 * its widgets in order as [{type, data}, ...]; each type has a form (the
 * admin side) and a Vue component Widgets/{Component}.vue (the public side).
 */
abstract class Widget
{
    /**
     * Stored in the page content; never change it once pages use it.
     */
    abstract public static function type(): string;

    abstract public static function label(): string;

    /**
     * Name of the Vue component in Modules/Site/resources/js/Widgets.
     */
    abstract public static function component(): string;

    /**
     * @return array<Component>
     */
    abstract public static function schema(): array;

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedSquare2Stack;
    }

    /**
     * Props for the Vue component, from the stored data. Runs on every page
     * view, so it is the place to make stored data safe to show.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function props(array $data): array
    {
        return $data;
    }

    public static function block(): Block
    {
        return Block::make(static::type())
            ->label(static::label())
            ->icon(static::icon())
            ->schema(static::schema());
    }
}
