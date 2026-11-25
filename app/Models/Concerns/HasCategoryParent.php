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

        return $this->getConnection()->transaction(function () use ($options): bool {
            $connection = $this->getConnection();
            $tree = $this->newModelQuery();

            // Serialize moves within a tree so concurrent parent changes cannot form a cycle.
            if ($this->categoryTreeIsTenantScoped()) {
                $tenantId = $this->tenant_id ?? Tenant::current()?->id;
                $connection->table('tenants')->where('id', $tenantId)->lock('for no key update')->first();
                $tree->where('tenant_id', $tenantId);
            } else {
                $connection->select('select pg_advisory_xact_lock(hashtext(?))', [$this->getTable()]);
            }

            $parents = $tree->pluck('parent_id', 'id')->all();
            $visited = $this->exists ? [$this->getKey() => true] : [];
            $ancestorId = $this->parent_id;

            while ($ancestorId !== null) {
                if (isset($visited[$ancestorId]) || ! array_key_exists($ancestorId, $parents)) {
                    throw ValidationException::withMessages(['parent_id' => $this->categoryTreeIsTenantScoped()
                        ? 'دستهٔ والد باید در همین شعبه و خارج از زیرشاخه‌های این دسته باشد.'
                        : 'دستهٔ والد باید معتبر و خارج از زیرشاخه‌های این دسته باشد.']);
                }

                $visited[$ancestorId] = true;
                $ancestorId = $parents[$ancestorId];
            }

            return parent::save($options);
        });
    }

    protected function categoryTreeIsTenantScoped(): bool
    {
        return true;
    }
}
