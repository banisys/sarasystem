<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

trait HasCategoryParent
{
    public function save(array $options = []): bool
    {
        if ($this->parent_id === null || ! $this->isDirty(['parent_id', 'tenant_id'])) {
            return parent::save($options);
        }

        $tenantId = $this->tenant_id ?? Tenant::current()?->id;

        return $this->getConnection()->transaction(function () use ($tenantId, $options): bool {
            // Serialize additions to a tenant's tree so two concurrent moves cannot form a cycle.
            $this->getConnection()->table('tenants')->where('id', $tenantId)->lock('for no key update')->first();
            $parents = $this->newModelQuery()->where('tenant_id', $tenantId)->pluck('parent_id', 'id')->all();
            $visited = $this->exists ? [$this->getKey() => true] : [];
            $ancestorId = $this->parent_id;

            while ($ancestorId !== null) {
                if (isset($visited[$ancestorId]) || ! array_key_exists($ancestorId, $parents)) {
                    throw ValidationException::withMessages(['parent_id' => 'دستهٔ والد باید در همین شعبه و خارج از زیرشاخه‌های این دسته باشد.']);
                }

                $visited[$ancestorId] = true;
                $ancestorId = $parents[$ancestorId];
            }

            return parent::save($options);
        });
    }
}
