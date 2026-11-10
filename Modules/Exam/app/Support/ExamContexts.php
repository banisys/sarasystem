<?php

namespace Modules\Exam\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Exam\Interfaces\ExamContextSource;

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

    public function forParticipant(Builder $query, User $user): void
    {
        foreach ($this->sources as $type => $source) {
            $query->orWhere(fn (Builder $contexts) => $contexts
                ->where('context_type', $type)
                ->whereIn('context_id', $source->participantIds($user)));
        }
    }

    public function url(string $type, int $id): string
    {
        return route('exams.context', ['type' => $type, 'context' => $id]);
    }
}
