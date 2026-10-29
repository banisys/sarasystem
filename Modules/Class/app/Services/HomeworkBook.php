<?php

namespace Modules\Class\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\HomeworkSubmission;

/**
 * Handing in and grading homework (ADR 0030). The session's attendees hand
 * in an answer and/or files from «کلاس‌های من» and may change them until
 * graded; after the due day it is marked late (or refused, by config). The
 * session's teacher or substitute, the class's members whose role records
 * content, and whoever may edit the class give a score and feedback, at
 * any time.
 */
class HomeworkBook
{
    public const MAX_FILES = 5;

    public function __construct(private AttendanceRegister $register, private SessionAccess $access) {}

    /**
     * Why the user can't hand in (or change) the session's homework, or null.
     */
    public function submissionRefusal(User $user, ClassSession $session): ?string
    {
        if ($session->homework === null || $session->status === SessionStatus::Cancelled) {
            return 'این جلسه تکلیفی ندارد.';
        }

        if (! $this->register->attendees($session)->contains(fn (User $attendee): bool => $attendee->is($user))) {
            return 'این تکلیف برای شما نیست.';
        }

        if ($this->submissionOf($user, $session)?->isGraded()) {
            return 'تکلیف شما نمره گرفته و دیگر تغییر نمی‌کند.';
        }

        if (! config('class.homework.accepts_late') && $session->homework_due_on !== null && now()->greaterThan($session->homework_due_on->copy()->endOfDay())) {
            return 'مهلت تحویل این تکلیف گذشته است.';
        }

        return null;
    }

    public function canSubmit(User $user, ClassSession $session): bool
    {
        return $this->submissionRefusal($user, $session) === null;
    }

    public function canGrade(User $user, ClassSession $session): bool
    {
        if ($session->homework === null) {
            return false;
        }

        return ($session->classroom !== null && $user->can('update', $session->classroom))
            || $this->access->isStaffOf($user, $session, 'records_content');
    }

    public function submissionOf(User $user, ClassSession $session): ?HomeworkSubmission
    {
        return HomeworkSubmission::withoutTenantScope()->where('session_id', $session->getKey())->where('user_id', $user->getKey())->first();
    }

    /**
     * @param  list<UploadedFile>  $files
     * @param  list<int|string>  $removeMediaIds
     *
     * @throws ClassException
     */
    public function submit(ClassSession $session, User $learner, ?string $answer, array $files = [], array $removeMediaIds = []): HomeworkSubmission
    {
        if (($refusal = $this->submissionRefusal($learner, $session)) !== null) {
            throw new ClassException($refusal);
        }

        $submission = $this->submissionOf($learner, $session) ?? new HomeworkSubmission([
            'tenant_id' => $session->tenant_id,
            'classroom_id' => $session->classroom_id,
            'session_id' => $session->getKey(),
            'user_id' => $learner->getKey(),
        ]);
        $answer = filled($answer) ? trim($answer) : null;
        $keptFiles = $submission->exists
            ? $submission->getMedia(HomeworkSubmission::FILES_COLLECTION)->whereNotIn('id', array_map('intval', $removeMediaIds))->count()
            : 0;

        if ($answer === null && $files === [] && $keptFiles === 0) {
            throw new ClassException('پاسخ را بنویسید یا فایلی بفرستید.');
        }

        if ($keptFiles + count($files) > self::MAX_FILES) {
            throw new ClassException('بیشتر از '.self::MAX_FILES.' فایل نمی‌شود.');
        }

        return DB::transaction(function () use ($submission, $answer, $files, $removeMediaIds): HomeworkSubmission {
            $submission->fill(['answer' => $answer, 'submitted_at' => now()])->save();

            $submission->getMedia(HomeworkSubmission::FILES_COLLECTION)
                ->whereIn('id', array_map('intval', $removeMediaIds))
                ->each->delete();

            foreach ($files as $file) {
                $submission->addMedia($file)->toMediaCollection(HomeworkSubmission::FILES_COLLECTION);
            }

            return $submission;
        });
    }

    /**
     * Scores and feedback of handed-in homework; an empty score and
     * feedback take a grade back.
     *
     * @param  array<int, array{score?: int|float|string|null, feedback?: string|null}>  $grades  submission id => grade
     *
     * @throws ClassException
     */
    public function grade(ClassSession $session, array $grades, User $by): void
    {
        if (! $this->canGrade($by, $session)) {
            throw new ClassException('شما تکالیف این جلسه را نمره نمی‌دهید.');
        }

        $max = $this->maxScore($session);
        $submissions = $session->homeworkSubmissions()->withoutGlobalScopes()->whereKey(array_keys($grades))->get()->keyBy('id');

        if ($submissions->count() !== count($grades)) {
            throw new ClassException('این تکلیف از این جلسه نیست.');
        }

        DB::transaction(function () use ($grades, $submissions, $max, $by): void {
            foreach ($grades as $id => $grade) {
                $score = filled($grade['score'] ?? null) ? (float) $grade['score'] : null;
                $feedback = filled($grade['feedback'] ?? null) ? trim($grade['feedback']) : null;

                if ($score !== null && ($score < 0 || $score > $max)) {
                    throw new ClassException("نمره باید بین ۰ و {$max} باشد.");
                }

                $submission = $submissions[$id];
                $graded = $score !== null || $feedback !== null;
                $unchanged = $submission->feedback === $feedback && ($submission->score === null ? $score === null : (float) $submission->score === $score);

                if (! $unchanged) {
                    $submission->update([
                        'score' => $score,
                        'feedback' => $feedback,
                        'graded_by_id' => $graded ? $by->getKey() : null,
                        'graded_at' => $graded ? now() : null,
                    ]);
                }
            }
        });
    }

    public function maxScore(ClassSession $session): int
    {
        return $session->homework_max_score ?? (int) config('class.homework.default_max_score');
    }
}
