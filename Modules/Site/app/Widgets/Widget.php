<?php

namespace Modules\Site\Widgets;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * One kind of building block for a page's content (ADR 0011). A page stores
 * its widgets in order as [{type, data}, ...]; each type has a form (the
 * admin side) and a Vue component Widgets/{Component}.vue (the public side).
 * Every widget also has a title, "show title" and "visible" (common fields).
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
     * The widget's own fields, after the common ones.
     *
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
            // In the picker (no state) the type; on an item its title.
            ->label(fn (?array $state): string|HtmlString => $state === null ? static::label() : static::itemLabel($state))
            ->icon(static::icon())
            ->schema([
                TextInput::make('title')
                    ->label('عنوان')
                    ->required()
                    ->maxLength(255),
                Toggle::make('show_title')
                    ->label('نمایش عنوان در سایت')
                    ->default(false),
                Toggle::make('is_visible')
                    ->label('نمایش در سایت')
                    ->default(true),
                ...static::schema(),
            ])
            // Filament edits blocks in a modal only when they have a preview;
            // the page builder hides it (headers only).
            ->preview('site::filament.widget-preview');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function isVisible(array $data): bool
    {
        return (bool) ($data['is_visible'] ?? true);
    }

    /**
     * Label of a widget in the page builder: its title, then its type.
     *
     * @param  array<string, mixed>  $data
     */
    public static function itemLabel(array $data): HtmlString
    {
        return new HtmlString(view('site::filament.widget-label', [
            'title' => filled($data['title'] ?? null) ? $data['title'] : static::label(),
            'type' => static::label(),
            'isVisible' => static::isVisible($data),
        ])->render());
    }
}
