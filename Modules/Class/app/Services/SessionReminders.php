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

    public function canRead(User $user, ClassSession $source): bool
    {
        return $this->staffSessions($user)->whereKey($source->getKey())->exists();
    }

    /** @return array<string, mixed>|null */
    public function timeline(User $user, ClassSession $source): ?array
    {
        if (! $this->canRead($user, $source)) {
            return null;
        }

        $source->loadMissing('classroom.term', 'reminders.author');
        $upcoming = $this->targets($user, $source)
            ->with('classroom', 'reminders.author')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get();

        return [
            'term' => $source->classroom->term?->title,
            'max_length' => self::MAX_LENGTH,
            'sessions' => [
                $this->entry($source, isCurrent: true),
                ...$upcoming->map(fn (ClassSession $session): array => $this->entry($session, isCurrent: false)),
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
        $termId = $source->classroom->term_id;

        return $this->staffSessions($user)
            ->upcoming()
            ->where('status', SessionStatus::Scheduled)
            ->where('starts_at', '>', $source->starts_at)
            ->where('subject_id', $source->subject_id)
            ->when(
                $source->subject_id !== null && $termId !== null,
                fn (Builder $query) => $query->whereRelation('classroom', 'term_id', $termId),
                fn (Builder $query) => $query->where('classroom_id', $source->classroom_id),
            );
    }

    /** @return array<string, mixed> */
    private function entry(ClassSession $session, bool $isCurrent): array
    {
        return [
            'id' => $session->getKey(),
            'title' => $session->title,
            'class' => $session->classroom->title,
            'when' => MyClasses::jalali($session->starts_at->toImmutable(), 'l Y/m/d H:i'),
            'is_current' => $isCurrent,
            'reminders' => $session->reminders->map(fn (ClassSessionReminder $reminder): array => [
                'id' => $reminder->getKey(),
                'body' => $reminder->body,
                'author' => $reminder->author?->name,
                'recorded_at' => MyClasses::jalali($reminder->created_at->toImmutable(), 'Y/m/d H:i'),
            ])->all(),
        ];
    }
}
