<?php

namespace Modules\Exam\Support;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Exam\Interfaces\ExamContextSource;
use Modules\Exam\Interfaces\SharedExamContextSource;

/** Optional hosts register here; missing/disabled hosts fail closed. */
class ExamContexts
{
    /** @var array<string, ExamContextSource> */
    private array $sources = [];

    public function register(string $type, ExamContextSource $source): void
    {
        $this->sources[$type] = $source;
    }

    public function resolve(string $type, int $id, User $user): ?ExamContext
    {
        return ($this->sources[$type] ?? null)?->resolve($id, $user);
    }

    public function tenantForVisit(string $type, int $id, User $user): ?Tenant
    {
        return ($this->sources[$type] ?? null)?->tenantForVisit($id, $user);
    }

    /** @return list<string> */
    public function sharedTypes(): array
    {
        return array_keys(array_filter($this->sources, fn (ExamContextSource $source): bool => $source instanceof SharedExamContextSource));
    }

    public function sharedTenantForVisit(string $type, int $id, User $user): ?Tenant
    {
        $source = $this->sources[$type] ?? null;

        return $source instanceof SharedExamContextSource ? $source->tenantForVisit($id, $user) : null;
    }

    public function forParticipant(Builder $query, User $user): void
    {
        foreach ($this->sources as $type => $source) {
            $query->orWhere(fn (Builder $contexts) => $contexts
                ->where('context_type', $type)
                ->whereIn('context_id', $source->participantIds($user)));
        }
    }

    public function hasTeachingStaff(User $user): bool
    {
        foreach ($this->sources as $source) {
            if ($source->hasTeachingStaff($user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $contextIds
     * @return array<int, array{id: int, title: string}>
     */
    public function subjectsFor(string $type, array $contextIds): array
    {
        return ($this->sources[$type] ?? null)?->subjectsFor($contextIds) ?? [];
    }

    public function url(string $type, int $id): string
    {
        return route('exams.context', ['type' => $type, 'context' => $id]);
    }
}
