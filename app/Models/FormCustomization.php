<?php

namespace App\Models;

use App\CustomFields\BuiltInField;
use App\CustomFields\CustomField;
use App\CustomFields\FieldMode;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * How a tenant set up a customizable form (ADR 0017): for the whole form
 * (`owner` null) or for one record such as a job position. Read through
 * App\CustomFields\CustomizableForm::resolve().
 *
 * @property array<string, string> $field_modes
 * @property list<array<string, mixed>> $fields
 */
#[Fillable(['tenant_id', 'form', 'owner_type', 'owner_id', 'field_modes', 'fields'])]
class FormCustomization extends Model
{
    use BelongsToTenant;

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The mode chosen for a built-in field, if any and still allowed.
     */
    public function modeFor(BuiltInField $field): ?FieldMode
    {
        $mode = FieldMode::tryFrom((string) ($this->field_modes[$field->key] ?? ''));

        return $mode !== null && $field->allows($mode) ? $mode : null;
    }

    /**
     * @return list<CustomField>
     */
    public function customFields(): array
    {
        return array_map(fn (array $field): CustomField => CustomField::fromArray($field), array_values($this->fields ?? []));
    }

    protected function casts(): array
    {
        return [
            'field_modes' => 'array',
            'fields' => 'array',
        ];
    }
}
