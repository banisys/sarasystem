<?php

namespace Modules\Class\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\Class\Enums\SessionStatus;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\ClassSessionReminder;

class SessionReminders
{
    public const MAX_LENGTH = 2000;

    public const PAGE_SIZE = 3;

    public function canRead(User $user, ClassSession $source): bool
    {
        return $this->staffSessions($user)->whereKey($source->getKey())->exists();
    }

    /** @return array<string, mixed>|null */
    public function timeline(User $user, ClassSession $source, int $previousCount, int $upcomingCount): ?array
    {
        if (! $this->canRead($user, $source)) {
            return null;
        }

        $source->loadMissing('classroom.term', 'reminders.author');
        $previousCount = min(max(0, $previousCount), PHP_INT_MAX - self::PAGE_SIZE);
        $upcomingCount = min(max(self::PAGE_SIZE, $upcomingCount), PHP_INT_MAX - self::PAGE_SIZE);
        $previous = $this->relatedSessions($user, $source)
            ->takingPlace()
            ->where('starts_at', '<', $source->starts_at)
            ->with('reminders.author')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit($previousCount + 1)
            ->get();
        $upcoming = $this->targets($user, $source)
            ->with('reminders.author')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->limit($upcomingCount + 1)
            ->get();

        return [
            'term' => $source->classroom->term?->title,
            'max_length' => self::MAX_LENGTH,
            'counts' => ['previous_reminders' => $previousCount, 'upcoming_reminders' => $upcomingCount],
            'more_previous' => $previous->count() > $previousCount ? $previousCount + self::PAGE_SIZE : null,
            'more_upcoming' => $upcoming->count() > $upcomingCount ? $upcomingCount + self::PAGE_SIZE : null,
            'has_upcoming' => $upcoming->isNotEmpty(),
            'sessions' => [
                ...$previous->take($previousCount)->reverse()->map(fn (ClassSession $session): array => $this->entry($session, isCurrent: false, canAdd: false)),
                $this->entry($source, isCurrent: true),
                ...$upcoming->take($upcomingCount)->map(fn (ClassSession $session): array => $this->entry($session, isCurrent: false, canAdd: true)),
            ],
        ];
    }

    public function add(User $user, ClassSession $source, ClassSession $target, string $body): void
    {
        DB::transaction(function () use ($user, $source, $target, $body): void {
            $sessions = ClassSession::query()->whereKey([$source->getKey(), $target->getKey()])
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            $source = $sessions->get($source->getKey());
            $target = $sessions->get($target->getKey());

            abort_unless($source && $target && $this->canRead($user, $source), 404);
            abort_unless($this->targets($user, $source)->whereKey($target->getKey())->exists(), 404);

            $target->reminders()->create([
                'source_session_id' => $source->getKey(),
                'author_id' => $user->getKey(),
                'body' => $body,
            ]);
        }, attempts: 3);
    }

    /** @return Builder<ClassSession> */
    private function staffSessions(User $user): Builder
    {
        return ClassSession::query()->where(fn (Builder $query) => $query
            ->taughtBy($user)
            ->orWhereHas('classroom.members', fn (Builder $member) => $member
                ->where('user_id', $user->getKey())
                ->current()
                ->where(fn (Builder $member) => $member->whereNull('joined_on')->orWhereDate('joined_on', '<=', today()))
                ->whereHas('classRole', fn (Builder $role) => $role->where('is_attendee', false))));
    }

    /** @return Builder<ClassSession> */
    private function targets(User $user, ClassSession $source): Builder
    {
        return $this->relatedSessions($user, $source)
            ->upcoming()
            ->where('status', SessionStatus::Scheduled)
            ->where('starts_at', '>', $source->starts_at);
    }

    /** @return Builder<ClassSession> */
    private function relatedSessions(User $user, ClassSession $source): Builder
    {
        return $this->staffSessions($user)
            ->where('classroom_id', $source->classroom_id)
            ->where('subject_id', $source->subject_id);
    }

    /** @return array<string, mixed> */
    private function entry(ClassSession $session, bool $isCurrent, bool $canAdd = false): array
    {
        return [
            'id' => $session->getKey(),
            'date' => MyClasses::jalali($session->starts_at->toImmutable(), 'l Y/m/d'),
            'is_current' => $isCurrent,
            'can_add' => $canAdd,
            'reminders' => $session->reminders->map(fn (ClassSessionReminder $reminder): array => [
                'id' => $reminder->getKey(),
                'body' => $reminder->body,
                'author' => $reminder->author?->name,
                'recorded_at' => MyClasses::jalali($reminder->created_at->toImmutable(), 'Y/m/d H:i'),
            ])->all(),
        ];
    }
}
