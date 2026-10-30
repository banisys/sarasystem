<?php

namespace App\CustomFields;

use App\Models\FormCustomization;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A form whose fields each organization may adjust (ADR 0017): built-in
 * fields can be made optional or left out, and extra fields can be added,
 * for the whole form and per record of an owner (e.g. per job position).
 * Modules register theirs with CustomForms::register().
 */
abstract class CustomizableForm
{
    /**
     * The form's key in `form_customizations.form`, e.g. `employee.job_application`.
     */
    abstract public function key(): string;

    abstract public function label(): string;

    /**
     * Where added fields can go: section key => heading, in form order.
     *
     * @return array<string, string>
     */
    abstract public function sections(): array;

    /**
     * @return list<BuiltInField>
     */
    public function builtInFields(): array
    {
        return [];
    }

    public function builtInField(string $key): ?BuiltInField
    {
        return collect($this->builtInFields())->firstWhere('key', $key)
            ?? collect(AdditionalBuiltInFields::for($this->key()))->firstWhere('key', $key);
    }

    /**
     * @return list<BuiltInField>
     */
    public function allBuiltInFields(): array
    {
        return [...$this->builtInFields(), ...AdditionalBuiltInFields::for($this->key())];
    }

    /**
     * The form as set up in the tenant, for an owner record if given: the
     * owner's choices win over the whole form's, and the owner's added
     * fields come after the whole form's ones.
     */
    public function resolve(Tenant $tenant, ?Model $owner = null): ResolvedForm
    {
        // The tenant is explicit: public forms are validated before any
        // tenant is current.
        $rows = FormCustomization::withoutTenantScope()
            ->where('tenant_id', $tenant->getKey())
            ->where('form', $this->key())
            ->where(fn (Builder $query) => $query
                ->whereNull('owner_type')
                ->when($owner !== null, fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query
                    ->where('owner_type', $owner->getMorphClass())
                    ->where('owner_id', $owner->getKey()))))
            ->get();

        $general = $rows->first(fn (FormCustomization $row): bool => $row->owner_type === null);
        $own = $rows->first(fn (FormCustomization $row): bool => $row->owner_type !== null);

        $modes = [];

        foreach ($this->allBuiltInFields() as $field) {
            $modes[$field->key] = $own?->modeFor($field) ?? $general?->modeFor($field) ?? $field->default;
        }

        return new ResolvedForm($this, $modes, [
            ...($general?->customFields() ?? []),
            ...($own?->customFields() ?? []),
        ]);
    }
}
