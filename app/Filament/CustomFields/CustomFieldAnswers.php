<?php

namespace App\Filament\CustomFields;

use App\CustomFields\CustomField;
use App\CustomFields\FieldType;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Illuminate\Database\Eloquent\Model;

/**
 * Infolist section with the answers to a form's added fields (ADR 0017),
 * read from the copy of each field stored with its answer.
 */
class CustomFieldAnswers
{
    public static function section(string $attribute, string $heading = 'اطلاعات تکمیلی'): Section
    {
        return Section::make($heading)
            ->columns(2)
            ->visible(fn (Model $record): bool => filled($record->getAttribute($attribute)))
            ->schema(fn (Model $record): array => collect($record->getAttribute($attribute) ?? [])
                ->values()
                ->map(fn (array $answer, int $index): TextEntry => TextEntry::make("{$attribute}_{$index}")
                    ->label($answer['label'] ?? '')
                    ->state(CustomField::fromArray($answer)->display($answer['value'] ?? null))
                    ->placeholder('—')
                    ->columnSpan(($answer['type'] ?? null) === FieldType::Textarea->value ? 'full' : 1))
                ->all());
    }
}
