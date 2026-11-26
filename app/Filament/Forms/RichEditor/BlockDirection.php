<?php

namespace App\Filament\Forms\RichEditor;

use DOMElement;
use Tiptap\Core\Extension;

class BlockDirection extends Extension
{
    public static $name = 'blockDirection';

    public function addGlobalAttributes(): array
    {
        return [
            [
                'types' => ['paragraph', 'heading', 'blockquote', 'listItem'],
                'attributes' => [
                    'dir' => [
                        'default' => null,
                        'parseHTML' => function (DOMElement $element): ?string {
                            $direction = $element->getAttribute('dir');

                            return in_array($direction, ['rtl', 'ltr', 'auto'], true) ? $direction : null;
                        },
                        'renderHTML' => fn (object $attributes): array => isset($attributes->dir)
                            ? ['dir' => $attributes->dir]
                            : [],
                    ],
                ],
            ],
        ];
    }
}
