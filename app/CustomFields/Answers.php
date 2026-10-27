<?php

namespace App\CustomFields;

/**
 * Stored answers to added fields (ADR 0017): lists of
 * `{key, label, type, section, value}`, each with a copy of its field.
 */
final class Answers
{
    /**
     * Answers whose value differs, as text for a history: the label is the
     * newer copy's, values as staff read them.
     *
     * @param  list<array<string, mixed>>|null  $old
     * @param  list<array<string, mixed>>|null  $new
     * @return array<string, array{label: string, old: string|null, new: string|null}> keyed by `custom.{key}`
     */
    public static function changes(?array $old, ?array $new): array
    {
        $old = collect($old ?? [])->keyBy('key');
        $changes = [];

        foreach ($new ?? [] as $answer) {
            $previous = $old->get($answer['key']);
            $field = CustomField::fromArray($answer);
            $before = $previous === null ? null : CustomField::fromArray($previous)->display($previous['value'] ?? null);
            $after = $field->display($answer['value'] ?? null);

            if ($before !== $after) {
                $changes[ResolvedForm::INPUT.'.'.$answer['key']] = ['label' => $field->label, 'old' => $before, 'new' => $after];
            }
        }

        return $changes;
    }
}
