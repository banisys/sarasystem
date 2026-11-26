<?php

namespace App\Filament\Forms\RichEditor;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\HasToolbarButtons;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;

class AdminRichEditorPlugin implements HasToolbarButtons, RichContentPlugin
{
    public function getTipTapPhpExtensions(): array
    {
        return [new BlockDirection];
    }

    public function getTipTapJsExtensions(): array
    {
        return [Vite::asset('resources/js/filament/rich-editor-block-direction.js')];
    }

    public function getEnabledToolbarButtons(): array
    {
        return [['textDirectionRtl', 'textDirectionLtr', 'htmlSource'], ['fullscreen']];
    }

    public function getDisabledToolbarButtons(): array
    {
        return [];
    }

    public function getEditorTools(): array
    {
        return [
            $this->directionTool('textDirectionRtl', 'راست‌به‌چپ', 'rtl', 'sara-text-direction-rtl'),
            $this->directionTool('textDirectionLtr', 'چپ‌به‌راست', 'ltr', 'sara-text-direction-ltr'),
            RichEditorTool::make('htmlSource')
                ->label('ویرایش منبع HTML')
                ->icon(Heroicon::OutlinedCodeBracket)
                ->action(arguments: '{ html: $getEditor().getHTML() }'),
            RichEditorTool::make('fullscreen')
                ->label('تمام‌صفحه')
                ->icon(Heroicon::OutlinedArrowsPointingOut)
                ->activeStyling(false)
                ->extraAttributes([
                    'x-data' => '{ expanded: false }',
                    'x-bind:aria-pressed' => "expanded ? 'true' : 'false'",
                    'x-on:keydown.escape.window.capture' => <<<'JS'
                        if (expanded && $el.closest('[data-rich-editor-key]').contains($event.target)) {
                            $event.stopImmediatePropagation()
                            expanded = false
                            $el.focus({ preventScroll: true })
                        }
                        JS,
                ])
                ->jsHandler('expanded = !expanded'),
        ];
    }

    public function getEditorActions(): array
    {
        return [
            Action::make('htmlSource')
                ->modalHeading('ویرایش منبع HTML')
                ->modalSubmitActionLabel('اعمال')
                ->modalWidth(Width::FourExtraLarge)
                ->fillForm(fn (array $arguments): array => [
                    'html' => $arguments['html'] ?? '',
                ])
                ->schema([
                    Textarea::make('html')
                        ->label('کد HTML')
                        ->rows(16)
                        ->extraInputAttributes(['dir' => 'ltr', 'class' => 'font-mono']),
                ])
                ->action(function (array $data, RichEditor $component): void {
                    $component->runCommands([
                        EditorCommand::make('setContent', arguments: [
                            Str::sanitizeHtml($data['html'] ?? ''),
                            ['emitUpdate' => true],
                        ]),
                    ]);
                }),
        ];
    }

    private function directionTool(string $name, string $label, string $direction, string $icon): RichEditorTool
    {
        return RichEditorTool::make($name)
            ->label($label)
            ->icon($icon)
            ->jsHandler("\$getEditor()?.chain().focus().setTextDirection('{$direction}').run()")
            ->activeJsExpression("\$getEditor()?.isActive({ dir: '{$direction}' })")
            ->toggle();
    }
}
