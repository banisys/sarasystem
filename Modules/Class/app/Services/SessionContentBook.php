<?php

namespace Modules\Class\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassSession;

/**
 * Recording what a session covered (ADR 0025): the plan topics it
 * finished, a summary, homework with a due date and materials. Taken by
 * the session's teacher or substitute, the class's members whose role
 * records content, and whoever may edit the class (SessionAccess).
 * Recording it marks the session held.
 */
class SessionContentBook
{
    public function __construct(private SessionAccess $access, private PlanForecast $forecast) {}

    public function refusal(User $user, ClassSession $session): ?string
    {
        return $this->access->refusal($user, $session, 'records_content', (int) config('class.content.locks_after_days'), 'شما مطالب جلسه‌های این کلاس را ثبت نمی‌کنید.');
    }

    public function canRecord(User $user, ClassSession $session): bool
    {
        return $this->refusal($user, $session) === null;
    }

    /**
     * @param  array{covered_topic_ids?: list<int|string>|null, summary?: string|null, homework?: string|null, homework_due_on?: string|null, homework_max_score?: int|string|null, files?: list<UploadedFile|string>|null, file_names?: array<string, string>|null, remove_media_ids?: list<int|string>|null}  $data  files: uploads, or paths on the local disk named in file_names
     *
     * @throws ClassException
     */
    public function record(ClassSession $session, array $data, User $by): ClassSession
    {
        if (($refusal = $this->refusal($by, $session)) !== null) {
            throw new ClassException($refusal);
        }

        $topicIds = array_values(array_unique(array_map('intval', $data['covered_topic_ids'] ?? [])));
        if (array_diff($topicIds, $this->forecast->topicChoices($session)->modelKeys()) !== []) {
            throw new ClassException('این سرفصل در طرح درس این کلاس نیست.');
        }

        $summary = filled($data['summary'] ?? null) ? trim($data['summary']) : null;
        if ($topicIds === [] && $summary === null) {
            throw new ClassException('سرفصلی را انتخاب کنید یا شرح مطالب را بنویسید.');
        }

        $homework = filled($data['homework'] ?? null) ? trim($data['homework']) : null;
        $maxScore = $homework === null ? null : (int) ($data['homework_max_score'] ?? 0);
        if ($maxScore !== null && $maxScore < 1) {
            $maxScore = (int) config('class.homework.default_max_score');
        }
        if ($maxScore !== null && $maxScore > 100) {
            throw new ClassException('نمره‌ی تکلیف حداکثر از ۱۰۰ است.');
        }
        $dueOn = $homework !== null && filled($data['homework_due_on'] ?? null) ? CarbonImmutable::parse($data['homework_due_on']) : null;
        if ($dueOn !== null && $dueOn->lessThan($session->starts_at->copy()->startOfDay())) {
            throw new ClassException('مهلت تکلیف پیش از جلسه است.');
        }

        DB::transaction(function () use ($session, $data, $by, $topicIds, $summary, $homework, $dueOn, $maxScore): void {
            $session->update([
                'covered_topic_ids' => $topicIds === [] ? null : $topicIds,
                'summary' => $summary,
                'homework' => $homework,
                'homework_due_on' => $dueOn,
                'homework_max_score' => $maxScore,
                'content_recorded_by_id' => $by->getKey(),
                'content_recorded_at' => now(),
                'status' => $session->status === SessionStatus::Scheduled ? SessionStatus::Held : $session->status,
            ]);

            $session->getMedia(ClassSession::MATERIALS_COLLECTION)
                ->whereIn('id', array_map('intval', $data['remove_media_ids'] ?? []))
                ->each->delete();

            foreach ($data['files'] ?? [] as $file) {
                if ($file instanceof UploadedFile) {
                    $session->addMedia($file)->toMediaCollection(ClassSession::MATERIALS_COLLECTION);

                    continue;
                }

                $adder = $session->addMediaFromDisk($file, 'local');
                // The name the uploader gave the file, if it is a usable one.
                $name = $data['file_names'][$file] ?? null;
                if (is_string($name) && $name !== '' && mb_check_encoding($name, 'UTF-8')) {
                    $adder->usingFileName($name)->usingName(pathinfo($name, PATHINFO_FILENAME));
                }
                $adder->toMediaCollection(ClassSession::MATERIALS_COLLECTION);
            }
        });

        $this->forecast->flush();

        return $session;
    }
}
