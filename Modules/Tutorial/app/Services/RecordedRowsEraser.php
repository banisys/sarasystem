<?php

namespace Modules\Tutorial\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Deletes rows noted by a tutorial run or a sample-data batch (ADR 0040,
 * ADR 0046), newest first and without model events, so guards such as
 * «a tenant's home page can't be deleted» don't stop it. Rows that still
 * have children are tried again after the others; if a row can't go
 * (a row that wasn't made with them still points at it), it throws and
 * the caller's transaction undoes everything.
 */
class RecordedRowsEraser
{
    /** @var array{count: int, users: list<int|string>, tenants: list<int|string>} */
    private array $deleted = ['count' => 0, 'users' => [], 'tenants' => []];

    /**
     * Call inside a transaction.
     *
     * @param  list<Model>  $records  rows with `model_type` and `model_id`, newest first
     * @return int the rows deleted
     *
     * @throws RuntimeException
     */
    public function erase(array $records): int
    {
        $this->deleted = ['count' => 0, 'users' => [], 'tenants' => []];
        $pending = $records;

        while ($pending !== []) {
            $left = array_values(array_filter($pending, fn (Model $record): bool => ! $this->delete($record)));

            if (count($left) === count($pending)) {
                throw new RuntimeException('Recorded rows are still in use: '.implode(', ', array_unique(array_map(fn (Model $record): string => class_basename($record->model_type), $left))).'.');
            }

            $pending = $left;
        }

        $this->deleteLeftovers($this->deleted['users'], $this->deleted['tenants']);

        return $this->deleted['count'];
    }

    private function delete(Model $record): bool
    {
        if (! class_exists($record->model_type) || ! is_subclass_of($record->model_type, Model::class)) {
            return true;
        }

        $model = new $record->model_type;
        $key = ctype_digit($record->model_id) ? (int) $record->model_id : $record->model_id;

        try {
            // A savepoint: a failed delete leaves the transaction usable.
            $count = DB::transaction(fn (): int => DB::connection($model->getConnectionName())
                ->table($model->getTable())
                ->where($model->getKeyName(), $key)
                ->delete());
        } catch (QueryException) {
            return false;
        }

        $this->deleted['count'] += $count;

        match ($record->model_type) {
            User::class => $this->deleted['users'][] = $key,
            Tenant::class => $this->deleted['tenants'][] = $key,
            default => null,
        };

        return true;
    }

    /**
     * Role assignments and notifications have no foreign key to their
     * user, and role assignments none to their tenant.
     *
     * @param  list<int|string>  $userIds
     * @param  list<int|string>  $tenantIds
     */
    private function deleteLeftovers(array $userIds, array $tenantIds): void
    {
        if ($userIds === [] && $tenantIds === []) {
            return;
        }

        $userType = (new User)->getMorphClass();
        $modelKey = config('permission.column_names.model_morph_key');
        $teamKey = config('permission.column_names.team_foreign_key');

        foreach ([config('permission.table_names.model_has_roles'), config('permission.table_names.model_has_permissions')] as $table) {
            DB::table($table)
                ->where(fn ($query) => $query
                    ->where(fn ($query) => $query->where('model_type', $userType)->whereIn($modelKey, $userIds))
                    ->orWhereIn($teamKey, $tenantIds))
                ->delete();
        }

        DB::table('notifications')->where('notifiable_type', $userType)->whereIn('notifiable_id', $userIds)->delete();
    }
}
