<?php

namespace Modules\Site\Widgets;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Free HTML, shown where the widget sits on the page. Sanitized on save and
 * again on every view: no scripts, event handlers or javascript: URLs;
 * https iframes (maps, videos) are allowed (ADR 0011).
 */
class HtmlWidget extends Widget
{
    public static function type(): string
    {
        return 'html';
    }

    public static function label(): string
    {
        return 'HTML';
    }

    public static function component(): string
    {
        return 'Html';
    }

    public static function icon(): Heroicon
    {
        return Heroicon::OutlinedCodeBracket;
    }

    public static function schema(): array
    {
        return [
            CodeEditor::make('html')
                ->label('کد HTML')
                ->language(Language::Html)
                ->helperText('اسکریپت، رویدادها (onclick و …) و لینک‌های javascript: حذف می‌شوند؛ iframe فقط با https.')
                ->required()
                ->dehydrateStateUsing(fn (?string $state): string => static::sanitize($state)),
        ];
    }

    public static function props(array $data): array
    {
        return ['html' => static::sanitize($data['html'] ?? null)];
    }

    public static function sanitize(?string $html): string
    {
        return static::sanitizer()->sanitize((string) $html);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        static $sanitizer;

        return $sanitizer ??= new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowElement('iframe', ['src', 'width', 'height', 'title', 'allow', 'allowfullscreen', 'loading', 'referrerpolicy'])
                ->allowAttribute('class', '*')
                ->allowAttribute('style', '*')
                ->allowAttribute('id', '*')
                ->allowAttribute('dir', '*')
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowMediaSchemes(['https'])
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->withMaxInputLength(200_000),
        );
    }
}
