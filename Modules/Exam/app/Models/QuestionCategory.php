<?php

namespace Modules\Exam\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Modules\Exam\Database\Factories\QuestionCategoryFactory;

#[Fillable(['tenant_id', 'parent_id', 'title'])]
class QuestionCategory extends Model
{
    /** @use HasFactory<QuestionCategoryFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'exam_question_categories';

    protected static function newFactory(): QuestionCategoryFactory
    {
        return QuestionCategoryFactory::new();
    }

    /** @return BelongsTo<QuestionCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<QuestionCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<Question, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'category_id');
    }

    public function save(array $options = []): bool
    {
        if ($this->parent_id === null || ! $this->isDirty(['parent_id', 'tenant_id'])) {
            return parent::save($options);
        }

        $tenantId = $this->tenant_id ?? Tenant::current()?->id;

        return $this->getConnection()->transaction(function () use ($tenantId, $options): bool {
            // Serialize additions to a tenant's tree so two concurrent moves cannot form a cycle.
            $this->getConnection()->table('tenants')->where('id', $tenantId)->lock('for no key update')->first();
            $parents = $this->newModelQuery()->where('tenant_id', $tenantId)->pluck('parent_id', 'id')->all();
            $visited = $this->exists ? [$this->getKey() => true] : [];
            $ancestorId = $this->parent_id;

            while ($ancestorId !== null) {
                if (isset($visited[$ancestorId]) || ! array_key_exists($ancestorId, $parents)) {
                    throw ValidationException::withMessages(['parent_id' => 'دستهٔ والد باید در همین شعبه و خارج از زیرشاخه‌های این دسته باشد.']);
                }

                $visited[$ancestorId] = true;
                $ancestorId = $parents[$ancestorId];
            }

            return parent::save($options);
        });
    }
}
