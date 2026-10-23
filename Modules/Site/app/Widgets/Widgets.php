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
     * Content with the common fields filled in, for the page builder form.
     * Widgets saved before those fields existed get the type as title and
     * stay visible (a Toggle would read a missing value as "off").
     *
     * @param  array<array{type?: string, data?: array<string, mixed>}>  $content
     * @return array<array{type?: string, data?: array<string, mixed>}>
     */
    public static function withDefaults(array $content): array
    {
        $widgets = static::all();

        return array_map(function (array $item) use ($widgets): array {
            $widget = $widgets[$item['type'] ?? ''] ?? null;

            if ($widget !== null) {
                $item['data'] = [
                    'title' => $widget::label(),
                    'show_title' => false,
                    'is_visible' => true,
                    ...array_filter($item['data'] ?? [], fn (mixed $value): bool => $value !== null),
                ];
            }

            return $item;
        }, $content);
    }

    /**
     * Stored content → what the public page renders. Hidden widgets and
     * unknown types (e.g. of a removed widget) are skipped.
     *
     * @param  list<array{type?: string, data?: array<string, mixed>}>  $content
     * @return list<array{component: string, title: string|null, props: array<string, mixed>}>
     */
    public static function render(array $content): array
    {
        $widgets = static::all();

        return collect($content)
            ->filter(fn (array $item): bool => isset($widgets[$item['type'] ?? '']) && Widget::isVisible($item['data'] ?? []))
            ->map(function (array $item) use ($widgets): array {
                $data = $item['data'] ?? [];

                return [
                    'component' => $widgets[$item['type']]::component(),
                    'title' => ($data['show_title'] ?? false) && filled($data['title'] ?? null) ? $data['title'] : null,
                    'props' => $widgets[$item['type']]::props($data),
                ];
            })
            ->values()
            ->all();
    }
}
