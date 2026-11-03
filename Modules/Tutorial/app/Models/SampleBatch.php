<?php

namespace Modules\Tutorial\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One «ساخت داده‌ی نمونه» (ADR 0046): a class with its members, schedule,
 * sessions and their history, made for showing a client how the system
 * works. Its code (e.g. «SMP-0003») marks what it made, and deleting the
 * batch deletes every row it made.
 *
 * @property array<string, mixed> $options
 */
#[Fillable(['code', 'title', 'tenant_id', 'user_id', 'options'])]
class SampleBatch extends Model
{
    protected $table = 'tutorial_sample_batches';

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SampleRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(SampleRecord::class, 'batch_id');
    }

    /**
     * Rows of the model made by this batch, from every tenant.
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

    protected function casts(): array
    {
        return [
            'options' => 'array',
        ];
    }
}
