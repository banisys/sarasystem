<?php

namespace Modules\Class\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Class\Models\Attendance;
use Modules\Class\Services\AbsenceNotices;

/**
 * Tells a learner's guardians about one absence (ADR 0029), on the queue so
 * a slow SMS provider doesn't hold up saving attendance. Tenant-aware (the
 * default): it runs in the tenant it was dispatched from. A failed send is
 * tried again; an absence corrected before the job runs sends nothing.
 */
class SendAbsenceNotice implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $attendanceId)
    {
        $this->afterCommit();
    }

    public function handle(AbsenceNotices $notices): void
    {
        $attendance = Attendance::query()->with('session')->find($this->attendanceId);

        if ($attendance !== null) {
            $notices->send($attendance);
        }
    }
}
