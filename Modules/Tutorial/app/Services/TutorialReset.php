<?php

namespace Modules\Tutorial\Services;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Modules\Tutorial\Models\TutorialRecord;
use Modules\Tutorial\Models\TutorialRun;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Starting a tutorial again (ADR 0040): deletes every row made in the run,
 * newest first, then the run itself. The rows are deleted without model
 * events, so guards such as «a tenant's home page can't be deleted» don't
 * stop it. All or nothing: if a row can't go (another row that wasn't
 * made in the run still points at it), nothing is deleted.
 */
class TutorialReset
{
    /** @var array{count: int, users: list<int>, tenants: list<int>} */
    private array $deleted = ['count' => 0, 'users' => [], 'tenants' => []];

    public function __construct(private TutorialMode $mode) {}

    /**
     * @return int the rows deleted
     */
    public function reset(TutorialRun $run): int
    {
        $deleted = $this->mode->withoutRecording(fn (): int => DB::transaction(function () use ($run): int {
            $pending = $run->records()->orderByDesc('id')->get()->all();
            $this->deleted = ['count' => 0, 'users' => [], 'tenants' => []];

            // Rows that still have uncounted children are tried again after the others.
            while ($pending !== []) {
                $left = array_values(array_filter($pending, fn (TutorialRecord $record): bool => ! $this->delete($record)));

                if (count($left) === count($pending)) {
                    throw new RuntimeException('Rows of the tutorial are still in use: '.implode(', ', array_unique(array_map(fn (TutorialRecord $record): string => class_basename($record->model_type), $left))).'.');
                }

                $pending = $left;
            }

            $this->deleteLeftovers($this->deleted['users'], $this->deleted['tenants']);
            $run->delete();

            return $this->deleted['count'];
        }));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->mode->leave();
        // The chosen tenant may be gone.
        session()->forget(ResolveCurrentTenant::SESSION_KEY);

        return $deleted;
    }

    private function delete(TutorialRecord $record): bool
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
     * @param  list<int>  $userIds
     * @param  list<int>  $tenantIds
     */
    private function deleteLeftovers(array $userIds, array $tenantIds): void
    {
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
