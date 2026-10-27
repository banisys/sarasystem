<?php

namespace App\Filament\CustomFields;

use App\CustomFields\CustomField;
use App\CustomFields\FieldType;
use App\CustomFields\ResolvedForm;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

/**
 * Filament inputs for a form's added fields (ADR 0017), so staff can
 * change the answers, e.g. in a workflow's «ویرایش اطلاعات». Each input's
 * state is at `custom.{key}`.
 */
class CustomFieldInputs
{
    /**
     * @return list<Field>
     */
    public static function components(ResolvedForm $form): array
    {
        return array_map(self::component(...), $form->fields);
    }

    public static function component(CustomField $field): Field
    {
        $name = ResolvedForm::INPUT.'.'.$field->key;
        $options = array_combine($field->options, $field->options);

        $component = match ($field->type) {
            FieldType::Text => TextInput::make($name)->maxLength(CustomField::TEXT_MAX),
            FieldType::Textarea => Textarea::make($name)->maxLength(CustomField::TEXTAREA_MAX)->rows(3)->columnSpanFull(),
            FieldType::Number => TextInput::make($name)->numeric(),
            FieldType::Select => Select::make($name)->options($options),
            FieldType::MultiSelect => CheckboxList::make($name)->options($options)->columns(3)->columnSpanFull(),
            FieldType::Checkbox => Checkbox::make($name),
            FieldType::Date => DatePicker::make($name)->jalali(),
        };

        // A required checkbox must be ticked, as on the form itself.
        $component = $field->type === FieldType::Checkbox
            ? ($field->required ? $component->rule('accepted') : $component)
            : $component->required($field->required);

        return $component->label($field->label)->helperText($field->help);
    }

    /**
     * Stored answers as the inputs' state: key => value.
     *
     * @param  list<array<string, mixed>>|null  $answers
     * @return array<string, mixed>
     */
    public static function state(?array $answers): array
    {
        return collect($answers ?? [])->mapWithKeys(fn (array $answer): array => [$answer['key'] => $answer['value'] ?? null])->all();
    }

    /**
     * The answers to store: the form's fields with the edited values (and
     * a fresh copy of each field), then earlier answers to fields the form
     * no longer has, unchanged.
     *
     * @param  array<string, mixed>  $state
     * @param  list<array<string, mixed>>|null  $previous
     * @return list<array<string, mixed>>
     */
    public static function answers(ResolvedForm $form, array $state, ?array $previous): array
    {
        $edited = array_map(fn (CustomField $field): array => [
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type->value,
            'section' => $field->section,
            'value' => self::normalize($field, $state[$field->key] ?? null),
        ], $form->fields);

        $keys = array_column($edited, 'key');
        $kept = array_filter($previous ?? [], fn (array $answer): bool => ! in_array($answer['key'] ?? null, $keys, true));

        return [...$edited, ...array_values($kept)];
    }

    /**
     * Like CustomField::normalize(), but the date picker's state is
     * already a Gregorian date.
     */
    private static function normalize(CustomField $field, mixed $value): mixed
    {
        if ($field->type === FieldType::Date) {
            return blank($value) ? null : CarbonImmutable::parse($value)->toDateString();
        }

        return $field->normalize($value);
    }
}
