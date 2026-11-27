<?php

namespace Modules\Class\Services;

use App\Models\User;
use App\Support\SessionLessonLibrary;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassSession;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SessionLessons
{
    public function __construct(private SessionAccess $access, private ?SessionLessonLibrary $library = null) {}

    /** @return array{can_manage: bool, lessons: list<array<string, mixed>>}|null */
    public function forViewer(ClassSession $session, User $viewer, bool $isOwn): ?array
    {
        if (! $this->availableIn($session)) {
            return null;
        }

        $ids = $session->shared_lesson_ids ?? [];
        $staff = $this->access->isClassStaff($viewer, $session);
        $canManage = $isOwn && $staff;
        if (! $canManage && ($ids === [] || (! $staff && ! $this->isOnRoster($session, $viewer)))) {
            return null;
        }

        $lessons = $this->library->sharedContent(
            $ids,
            fn (int $lesson, int $file): string => route('my-classes.lessons.file', [
                'session' => $session,
                'lesson' => $lesson,
                'file' => $file,
                'for' => $isOwn ? null : $viewer->getKey(),
            ]),
        );

        return ['can_manage' => $canManage, 'lessons' => $lessons];
    }

    public function authorizeEditing(ClassSession $session, User $user): void
    {
        abort_unless($this->library !== null && $session->kind !== SessionKind::Exam, 404);
        abort_unless($session->status !== SessionStatus::Cancelled && $this->access->isClassStaff($user, $session), 403);
    }

    /** @param list<string> $codes */
    public function save(ClassSession $session, User $user, array $codes): void
    {
        DB::transaction(function () use ($session, $user, $codes): void {
            $current = ClassSession::query()->lockForUpdate()->findOrFail($session->getKey());
            $this->authorizeEditing($current, $user);

            $idsByCode = $this->library->idsByCode($codes);
            foreach ($codes as $code) {
                if (! isset($idsByCode[$code])) {
                    throw ValidationException::withMessages(['lesson_codes' => "درس قابل اشتراک‌گذاری با کد «{$code}» پیدا نشد."]);
                }
            }

            $current->update([
                'shared_lesson_ids' => $codes === [] ? null : array_map(fn (string $code): int => $idsByCode[$code], $codes),
            ]);
        });
    }

    public function file(ClassSession $session, User $viewer, int $lesson, int $file): BinaryFileResponse
    {
        abort_unless(
            $this->availableIn($session)
            && in_array($lesson, $session->shared_lesson_ids ?? [], true)
            && ($this->access->isClassStaff($viewer, $session) || $this->isOnRoster($session, $viewer)),
            404,
        );

        return $this->library->sharedFile($lesson, $file);
    }

    private function availableIn(ClassSession $session): bool
    {
        return $this->library !== null && $session->kind !== SessionKind::Exam && $session->status !== SessionStatus::Cancelled;
    }

    private function isOnRoster(ClassSession $session, User $viewer): bool
    {
        return ClassSession::query()->whereKey($session->getKey())->attendedBy($viewer)->exists();
    }
}
