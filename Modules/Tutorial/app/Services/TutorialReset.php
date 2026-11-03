<?php

namespace Modules\Tutorial\Services;

use App\Http\Middleware\ResolveCurrentTenant;
use Illuminate\Support\Facades\DB;
use Modules\Tutorial\Models\TutorialRun;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Starting a tutorial again (ADR 0040): deletes every row made in the run,
 * newest first (RecordedRowsEraser), then the run itself. All or nothing:
 * if a row can't go (another row that wasn't made in the run still points
 * at it), nothing is deleted.
 */
class TutorialReset
{
    public function __construct(private TutorialMode $mode, private RecordedRowsEraser $eraser) {}

    /**
     * @return int the rows deleted
     *
     * @throws RuntimeException
     */
    public function reset(TutorialRun $run): int
    {
        $deleted = $this->mode->withoutRecording(fn (): int => DB::transaction(function () use ($run): int {
            $count = $this->eraser->erase($run->records()->orderByDesc('id')->get()->all());
            $run->delete();

            return $count;
        }));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->mode->leave();
        // The chosen tenant may be gone.
        session()->forget(ResolveCurrentTenant::SESSION_KEY);

        return $deleted;
    }
}
