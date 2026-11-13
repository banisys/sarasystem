<?php

namespace Modules\Class\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Class\Filament\Actions\SessionActions;
use Modules\Class\Filament\Resources\ClassSessions\ClassSessionResource;
use Modules\Class\Models\ClassMember;
use Modules\Class\Models\ClassSession;
use Modules\Exam\Interfaces\ExamContextSource;
use Modules\Exam\Support\ExamContext;
use Modules\Exam\Support\ExamContexts;

/** Class integrates through Exam's extension contract, without using its models. */
class SessionExamSource implements ExamContextSource
{
    public const TYPE = 'class-session';

    public function resolve(int $id, User $user): ?ExamContext
    {
        $session = ClassSession::query()->takingPlace()->with(['classroom', 'teachers'])->find($id);
        if ($session === null) {
            return null;
        }

        $canManage = app(SessionAccess::class)->isClassStaff($user, $session)
            || $user->can('update', $session->classroom);
        $canParticipate = $this->participantIds($user)->whereKey($id)->exists();
        if (! $canManage && ! $canParticipate) {
            return null;
        }

        return new ExamContext(
            self::TYPE, $id, $session->tenant_id,
            $session->classroom->title.' · '.SessionActions::when($session),
            $session->starts_at, $session->ends_at,
            app(MyClasses::class)->canSee($user, $session)
                ? route('my-classes.session', $session)
                : ClassSessionResource::detailsUrl($session, $session->tenant),
            $canManage, $canParticipate,
        );
    }

    public function participantIds(User $user): Builder
    {
        return ClassSession::query()->select('class_sessions.id')->takingPlace()
            ->whereExists(ClassMember::query()->selectRaw('1')->attendees()
                ->where('user_id', $user->getKey())
                ->whereColumn('class_members.classroom_id', 'class_sessions.classroom_id')
                ->where(fn (Builder $members) => $members->whereNull('joined_on')->orWhereRaw('joined_on <= class_sessions.starts_at::date'))
                ->where(fn (Builder $members) => $members->whereNull('left_on')->orWhereRaw('left_on >= class_sessions.starts_at::date')));
    }

    public function hasTeachingStaff(User $user): bool
    {
        return ClassMember::query()->where('user_id', $user->id)->current()
            ->where(fn (Builder $members) => $members->whereNull('joined_on')->orWhereDate('joined_on', '<=', today()))
            ->whereHas('classRole', fn (Builder $roles) => $roles->where('is_attendee', false))
            ->exists()
            || ClassSession::query()->takingPlace()->withTeacher($user)->exists();
    }

    public function link(ClassSession $session, User $user): ?string
    {
        return $this->resolve($session->getKey(), $user) === null
            ? null
            : app(ExamContexts::class)->url(self::TYPE, $session->getKey());
    }
}
