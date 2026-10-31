<?php

namespace Modules\Tutorial\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person's way through one tutorial scenario (ADR 0040): the missions
 * they did, and the rows they made on the way.
 *
 * @property array<string, string> $completed_missions
 */
#[Fillable(['user_id', 'scenario', 'completed_missions', 'completed_at'])]
class TutorialRun extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<TutorialRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(TutorialRecord::class, 'run_id');
    }

    /**
     * Rows of the model made in this run, from every tenant.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $model
     * @return Builder<TModel>
     */
    public function made(string $model): Builder
    {
        $ids = $this->records()->where('model_type', $model)->pluck('model_id');

        return $model::query()->withoutGlobalScopes()->whereKey($ids->all());
    }

    public function isCompleted(string $missionKey): bool
    {
        return array_key_exists($missionKey, $this->completed_missions ?? []);
    }

    protected function casts(): array
    {
        return [
            'completed_missions' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
