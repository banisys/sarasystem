<?php

namespace Modules\Class\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\Class\Enums\SessionKind;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassSession;

class ExamSeating
{
    public const MAX_ROWS = 30;

    public const MAX_COLUMNS = 20;

    public function __construct(private AttendanceRegister $attendance) {}

    public function canEdit(User $user, ClassSession $session): bool
    {
        return $session->kind === SessionKind::Exam
            && $session->status !== SessionStatus::Cancelled
            && $session->classroom !== null
            && $user->can('update', $session->classroom);
    }

    /**
     * A saved position whose attendee is no longer on the roster becomes empty.
     *
     * @param  list<int>  $attendeeIds
     * @return array{rows: int, columns: int, seats: list<int|null>}|null
     */
    public function current(ClassSession $session, array $attendeeIds): ?array
    {
        if ($session->kind !== SessionKind::Exam || $session->seat_layout === null) {
            return null;
        }

        $layout = $session->seat_layout;
        $allowed = array_fill_keys($attendeeIds, true);
        $layout['seats'] = array_map(fn (?int $id): ?int => $id !== null && isset($allowed[$id]) ? $id : null, $layout['seats']);

        return $layout;
    }

    /** @return array{rows: int, columns: int, seats: list<int|null>} */
    public function draft(ClassSession $session): array
    {
        $ids = $this->attendance->attendees($session)->modelKeys();
        $columns = max(4, min(self::MAX_COLUMNS, (int) ceil(sqrt(count($ids)))));
        $rows = max(1, min(self::MAX_ROWS, (int) ceil(count($ids) / $columns)));

        return $this->current($session, $ids) ?? [
            'rows' => $rows,
            'columns' => $columns,
            'seats' => array_fill(0, $rows * $columns, null),
        ];
    }

    /** @throws ClassException */
    public function save(ClassSession $session, User $by, mixed $layout): void
    {
        DB::transaction(function () use ($session, $by, $layout): void {
            $locked = ClassSession::query()->lockForUpdate()->findOrFail($session->getKey());
            abort_unless($this->canEdit($by, $locked), 403);

            $validator = Validator::make(['layout' => $layout], [
                'layout' => 'required|array:rows,columns,seats',
                'layout.rows' => 'required|integer|min:1|max:'.self::MAX_ROWS,
                'layout.columns' => 'required|integer|min:1|max:'.self::MAX_COLUMNS,
                'layout.seats' => 'required|array|list|max:'.(self::MAX_ROWS * self::MAX_COLUMNS),
                'layout.seats.*' => 'nullable|integer|distinct',
            ]);

            if ($validator->fails() || count($layout['seats']) !== (int) $layout['rows'] * (int) $layout['columns']) {
                throw new ClassException('چیدمان صندلی‌ها معتبر نیست. تعداد ردیف‌ها، صندلی‌ها و جای افراد را بررسی کنید.');
            }

            $seats = array_map(fn (int|string|null $id): ?int => $id === null ? null : (int) $id, $layout['seats']);
            $assigned = array_filter($seats, fn (?int $id): bool => $id !== null);
            if (array_diff($assigned, $this->attendance->attendees($locked)->modelKeys()) !== []) {
                throw new ClassException('فقط شرکت‌کنندگان همین امتحان را می‌توانید روی صندلی‌ها بنشانید.');
            }

            $locked->seat_layout = ['rows' => (int) $layout['rows'], 'columns' => (int) $layout['columns'], 'seats' => $seats];
            $locked->save();
            $session->seat_layout = $locked->seat_layout;
            $session->syncOriginalAttribute('seat_layout');
        });
    }
}
