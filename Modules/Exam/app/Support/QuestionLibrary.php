<?php

namespace Modules\Exam\Support;

use App\Authorization\DefaultRoles;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Exam\Models\Question;
use Modules\Exam\Models\QuestionBank;

class QuestionLibrary
{
    public function canAccess(User $user, Tenant $tenant): bool
    {
        return $user->can('ViewAny:QuestionBank')
            || Role::query()->whereKey($user->roleIdsIn($tenant))->where('name', DefaultRoles::INSTRUCTOR)->exists()
            || app(ExamContexts::class)->hasTeachingStaff($user);
    }

    /** @return Builder<QuestionBank> */
    public function banks(User $user): Builder
    {
        return QuestionBank::query()->where(fn (Builder $query) => $this->visibleTo($query, $user));
    }

    /** @return Builder<Question> */
    public function questions(User $user): Builder
    {
        return Question::query()->where(fn (Builder $query) => $this->visibleTo($query, $user));
    }

    /** @return BelongsToMany<Question, QuestionBank> */
    public function availableQuestions(QuestionBank $bank, User $user): BelongsToMany
    {
        return $bank->questions()->active()->where(fn (Builder $query) => $this->visibleTo($query, $user));
    }

    public function visibleTo(Builder $query, User $user): void
    {
        $query->where('owner_id', $user->id)->orWhere(fn (Builder $shared) => $shared->whereNotNull('owner_id')->where('is_shared', true));
    }
}
