<?php

namespace App\CustomFields;

/**
 * A customizable form as set up in one tenant (and for one owner record):
 * the mode of each built-in field and the added fields (ADR 0017).
 * Answers to added fields are sent under `custom.{key}`.
 */
final readonly class ResolvedForm
{
    public const INPUT = 'custom';

    /**
     * @param  array<string, FieldMode>  $modes  built-in field key => mode
     * @param  list<CustomField>  $fields
     */
    public function __construct(
        public CustomizableForm $form,
        public array $modes,
        public array $fields,
    ) {}

    public function mode(string $key): FieldMode
    {
        return $this->modes[$key] ?? FieldMode::Required;
    }

    public function asks(string $key): bool
    {
        return $this->mode($key) !== FieldMode::Hidden;
    }

    public function requires(string $key): bool
    {
        return $this->mode($key) === FieldMode::Required;
    }

    /**
     * Rules for a built-in field: nothing is taken from it when it is not
     * asked; otherwise required / nullable plus its own rules.
     *
     * @param  list<mixed>  $rules
     * @return list<mixed>
     */
    public function rulesFor(string $key, array $rules): array
    {
        return match ($this->mode($key)) {
            FieldMode::Hidden => ['exclude'],
            FieldMode::Optional => ['nullable', ...$rules],
            FieldMode::Required => ['required', ...$rules],
        };
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function customRules(): array
    {
        $rules = [self::INPUT => ['nullable', 'array']];

        foreach ($this->fields as $field) {
            $rules = [...$rules, ...$field->rules(self::INPUT.'.'.$field->key)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function customAttributes(): array
    {
        return collect($this->fields)
            ->mapWithKeys(fn (CustomField $field): array => [self::INPUT.'.'.$field->key => $field->label, self::INPUT.'.'.$field->key.'.*' => $field->label])
            ->all();
    }

    /**
     * The typed answers, cleaned before validation.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function prepareCustomInput(array $input): array
    {
        $prepared = [];

        foreach ($this->fields as $field) {
            if (array_key_exists($field->key, $input)) {
                $prepared[$field->key] = $field->prepare($input[$field->key]);
            }
        }

        return $prepared;
    }

    /**
     * The validated answers to store: each with a copy of its field.
     *
     * @param  array<string, mixed>  $validated  the `custom` input
     * @return list<array{key: string, label: string, type: string, section: string|null, value: mixed}>
     */
    public function answers(array $validated): array
    {
        return array_map(fn (CustomField $field): array => [
            'key' => $field->key,
            'label' => $field->label,
            'type' => $field->type->value,
            'section' => $field->section,
            'value' => $field->normalize($validated[$field->key] ?? null),
        ], $this->fields);
    }

    /**
     * For a Vue form: `modes` of built-in fields and the added `fields`.
     *
     * @return array{modes: array<string, string>, fields: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'modes' => array_map(fn (FieldMode $mode): string => $mode->value, $this->modes),
            'fields' => array_map(fn (CustomField $field): array => $field->toArray(), $this->fields),
        ];
    }
}
