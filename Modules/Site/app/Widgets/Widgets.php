<?php

namespace Modules\Site\Widgets;

use Filament\Forms\Components\Builder\Block;

/**
 * The widget types a page can use (ADR 0011). Add new types here.
 */
class Widgets
{
    /**
     * @return array<string, class-string<Widget>> type => class
     */
    public static function all(): array
    {
        return collect([
            HtmlWidget::class,
        ])->mapWithKeys(fn (string $widget): array => [$widget::type() => $widget])->all();
    }

    /**
     * @return list<Block>
     */
    public static function blocks(): array
    {
        return array_values(array_map(fn (string $widget): Block => $widget::block(), static::all()));
    }

    /**
     * Stored content → what the public page renders. Unknown types (e.g. of
     * a removed widget) are skipped.
     *
     * @param  list<array{type?: string, data?: array<string, mixed>}>  $content
     * @return list<array{component: string, props: array<string, mixed>}>
     */
    public static function render(array $content): array
    {
        $widgets = static::all();

        return collect($content)
            ->filter(fn (array $item): bool => isset($widgets[$item['type'] ?? '']))
            ->map(fn (array $item): array => [
                'component' => $widgets[$item['type']]::component(),
                'props' => $widgets[$item['type']]::props($item['data'] ?? []),
            ])
            ->values()
            ->all();
    }
}
