<?php

namespace App\CustomFields;

use App\Rules\JalaliDate;
use App\Support\Identifiers;
use Ariaieboy\Jalali\CalendarUtils;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * A field an organization added to a customizable form (ADR 0017), as
 * stored in `form_customizations.fields`. Answers keep a copy of it
 * (label, type) next to the value, so later changes to the form don't
 * change how old answers read.
 */
final readonly class CustomField
{
    public const TEXT_MAX = 255;

    public const TEXTAREA_MAX = 2000;

    /**
     * @param  list<string>  $options  choices of a select / multi-select field
     */
    public function __construct(
        public string $key,
        public string $label,
        public FieldType $type,
        public bool $required = false,
        public array $options = [],
        public ?string $help = null,
        public ?string $section = null,
    ) {}

    /**
     * A new field's key: random, so keys of the whole form and of a record
     * (e.g. a job position) never clash, and never shown to anyone.
     */
    public static function newKey(): string
    {
        return 'f'.Str::lower(Str::random(10));
    }

    /**
     * @param  array{key?: string|null, label?: string|null, type?: FieldType|string|null, required?: bool|null, options?: list<string>|null, help?: string|null, section?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        $type = $data['type'] ?? null;
        $type = $type instanceof FieldType ? $type : (FieldType::tryFrom((string) $type) ?? FieldType::Text);

        return new self(
            key: filled($data['key'] ?? null) ? (string) $data['key'] : self::newKey(),
            label: trim((string) ($data['label'] ?? '')),
            type: $type,
            required: (bool) ($data['required'] ?? false),
            options: $type->hasOptions()
                ? array_values(array_unique(array_filter(array_map(fn (mixed $option): string => trim((string) $option), $data['options'] ?? []), 'filled')))
                : [],
            help: filled($data['help'] ?? null) ? trim((string) $data['help']) : null,
            section: filled($data['section'] ?? null) ? (string) $data['section'] : null,
        );
    }

    /**
     * @return array{key: string, label: string, type: string, required: bool, options: list<string>, help: string|null, section: string|null}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'required' => $this->required,
            'options' => $this->options,
            'help' => $this->help,
            'section' => $this->section,
        ];
    }

    /**
     * Validation rules for the answer at $attribute (and its items, for a
     * multi-select).
     *
     * @return array<string, list<mixed>>
     */
    public function rules(string $attribute): array
    {
        $presence = $this->required ? 'required' : 'nullable';

        return match ($this->type) {
            FieldType::Text => [$attribute => [$presence, 'string', 'max:'.self::TEXT_MAX]],
            FieldType::Textarea => [$attribute => [$presence, 'string', 'max:'.self::TEXTAREA_MAX]],
            FieldType::Number => [$attribute => [$presence, 'numeric']],
            FieldType::Select => [$attribute => [$presence, 'string', Rule::in($this->options)]],
            FieldType::MultiSelect => [
                $attribute => [$presence, 'array'],
                "{$attribute}.*" => ['string', Rule::in($this->options)],
            ],
            // A required checkbox must be ticked, e.g. «قوانین را می‌پذیرم».
            FieldType::Checkbox => [$attribute => $this->required ? ['accepted'] : ['nullable', 'boolean']],
            FieldType::Date => [$attribute => [$presence, 'string', new JalaliDate]],
        };
    }

    /**
     * The typed answer before validation: trimmed, Persian digits read as
     * Latin where a number or date is expected.
     */
    public function prepare(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_values(array_map(fn (mixed $item): mixed => is_string($item) ? trim($item) : $item, $value));
        }

        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return in_array($this->type, [FieldType::Number, FieldType::Date], true) ? Identifiers::toLatinDigits($value) : $value;
    }

    /**
     * The validated answer as stored: numbers as numbers, dates as
     * Gregorian Y-m-d, checkboxes as booleans; empty answers as null.
     */
    public function normalize(mixed $value): mixed
    {
        if ($this->type === FieldType::Checkbox) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        if (blank($value)) {
            return null;
        }

        return match ($this->type) {
            FieldType::Number => $value + 0,
            FieldType::MultiSelect => array_values((array) $value),
            FieldType::Date => JalaliDate::toDate((string) $value)?->toDateString(),
            default => (string) $value,
        };
    }

    /**
     * A stored answer as text for staff: Jalali dates, «بله» / «خیر».
     */
    public function display(mixed $value): ?string
    {
        if ($this->type === FieldType::Checkbox) {
            return $value === null ? null : ($value ? 'بله' : 'خیر');
        }

        if (blank($value)) {
            return null;
        }

        return match ($this->type) {
            FieldType::MultiSelect => implode('، ', (array) $value),
            FieldType::Date => self::jalali((string) $value),
            default => (string) $value,
        };
    }

    private static function jalali(string $date): string
    {
        $date = CarbonImmutable::parse($date);
        [$year, $month, $day] = CalendarUtils::toJalali($date->year, $date->month, $date->day);

        return sprintf('%04d/%02d/%02d', $year, $month, $day);
    }
}
