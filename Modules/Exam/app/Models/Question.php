<?php

namespace Modules\Exam\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Modules\Exam\Database\Factories\QuestionFactory;
use Modules\Exam\Enums\QuestionType;

/**
 * A question of a tenant's question banks. A choice question keeps its
 * options in `options` (exactly one correct), each with a stable `key`
 * that attempts refer to.
 *
 * @property list<array{key: string, text: string, is_correct: bool}>|null $options
 */
#[Fillable(['tenant_id', 'type', 'body', 'options', 'score', 'is_active'])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'exam_questions';

    protected static function booted(): void
    {
        // A written question has no options.
        static::saving(function (self $question): void {
            if ($question->type === QuestionType::Descriptive) {
                $question->options = null;
            }
        });
    }

    protected static function newFactory(): QuestionFactory
    {
        return QuestionFactory::new();
    }

    /**
     * @return BelongsToMany<QuestionBank, $this>
     */
    public function banks(): BelongsToMany
    {
        return $this->belongsToMany(QuestionBank::class, 'exam_bank_question');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function correctOptionKey(): ?string
    {
        return collect($this->options ?? [])->firstWhere('is_correct', true)['key'] ?? null;
    }

    /**
     * Options are stored as a list, each with a key (kept when given, made
     * up otherwise); a descriptive question has none.
     */
    protected function options(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?array => $value === null ? null : json_decode($value, true),
            set: fn (?array $value): ?string => $value === null ? null : json_encode(
                collect($value)->values()->map(fn (array $option): array => [
                    'key' => filled($option['key'] ?? null) ? (string) $option['key'] : Str::lower(Str::random(8)),
                    'text' => (string) ($option['text'] ?? ''),
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                ])->all(),
                JSON_UNESCAPED_UNICODE,
            ),
        );
    }

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'score' => 'float',
            'is_active' => 'boolean',
        ];
    }
}
