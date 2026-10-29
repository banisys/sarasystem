<?php

namespace Modules\Class\Services;

use App\Models\Guardianship;
use App\Models\Tenant;
use App\Sms\SmsSender;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Enums\AttendanceStatus;
use Modules\Class\Jobs\SendAbsenceNotice;
use Modules\Class\Models\Attendance;
use Modules\Class\Models\ClassSession;

/**
 * Tells guardians about an absence by SMS (ADR 0029): for each attendee
 * newly marked absent, every guardian with «پیامک» on and a mobile gets one
 * message, once per absence. Attendees without such a guardian get none.
 * Sending runs on the queue (SendAbsenceNotice), after the attendance is
 * saved.
 */
class AbsenceNotices
{
    public function __construct(private SmsSender $sms) {}

    /**
     * Queues a notice for each attendee newly marked absent.
     *
     * @param  iterable<Attendance>  $attendances
     */
    public function queue(iterable $attendances): void
    {
        if (! config('class.attendance.absence_sms')) {
            return;
        }

        foreach ($attendances as $attendance) {
            if ($this->isDue($attendance)) {
                SendAbsenceNotice::dispatch($attendance->getKey());
            }
        }
    }

    /**
     * Sends one absence to the guardians and marks it told. Run by the
     * queued job: the attendance is read again, so a correction made
     * meanwhile sends nothing. A failing send throws, and the job tries
     * again.
     */
    public function send(Attendance $attendance): void
    {
        if (! config('class.attendance.absence_sms') || ! $this->isDue($attendance)) {
            return;
        }

        $guardianships = Guardianship::query()
            ->where('ward_id', $attendance->user_id)
            ->where('receives_sms', true)
            ->whereHas('guardian', fn (Builder $guardian) => $guardian->whereNotNull('mobile'))
            ->with(['guardian', 'ward'])
            ->get();

        if ($guardianships->isNotEmpty()) {
            $message = $this->message($attendance->session, $guardianships->first()->ward->name);

            foreach ($guardianships->pluck('guardian.mobile')->unique() as $mobile) {
                $this->sms->send($mobile, $message);
            }
        }

        $attendance->update(['absence_notified_at' => now()]);
    }

    private function isDue(Attendance $attendance): bool
    {
        return $attendance->status === AttendanceStatus::Absent && $attendance->absence_notified_at === null;
    }

    /**
     * E.g. «امیر محمدی در جلسه‌ی «هفتم الف» (ریاضی) شنبه 1405/08/09 ساعت 08:00 غایب بود. شعبه شمال - سرا سیستم».
     */
    public function message(ClassSession $session, string $wardName): string
    {
        $session->loadMissing(['classroom', 'subject']);
        $tenant = Tenant::query()->find($session->tenant_id);
        $when = MyClasses::jalali($session->starts_at->toImmutable(), 'l Y/m/d').' ساعت '.$session->starts_at->format('H:i');
        $subject = $session->subject ? " ({$session->subject->title})" : '';

        return "{$wardName} در جلسه‌ی «{$session->classroom->title}»{$subject} {$when} غایب بود. {$tenant?->name} - ".config('app.name');
    }
}
