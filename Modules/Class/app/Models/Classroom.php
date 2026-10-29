<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Class\Database\Factories\ClassroomFactory;
use Modules\Class\Enums\ClassMode;

/**
 * A class of a tenant (ADR 0022): a group of people who meet on a
 * schedule, e.g. «هفتم الف», «زبان انگلیسی - مقدماتی ۲» or «دوره‌ی ایمنی
 * کارگاه». It runs in a term, in a place, online or both, and its members
 * hold class roles. A class may have sub-classes (one level), e.g. a lab
 * group of a school class. School data (grade, subjects) are not here; a
 * School module adds them.
 */
#[Fillable([
    'tenant_id', 'term_id', 'parent_id', 'title', 'code', 'description', 'mode', 'location', 'meeting_url', 'capacity',
    'starts_on', 'ends_on', 'is_active',
])]
class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ClassroomFactory
    {
        return ClassroomFactory::new();
    }

    /**
     * @param  Builder<Classroom>  $query
     */
    public function scopeTopLevel(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Current members holding an attendee role (they fill the capacity).
     */
    public function attendeeCount(?int $exceptMemberId = null): int
    {
        return $this->members()->current()->attendees()
            ->when($exceptMemberId !== null, fn (Builder $query) => $query->whereKeyNot($exceptMemberId))
            ->count();
    }

    public function isFull(int $adding = 1, ?int $exceptMemberId = null): bool
    {
        return $this->capacity !== null && $this->attendeeCount($exceptMemberId) + $adding > $this->capacity;
    }

    /**
     * Terms are the primary tenant's.
     *
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Classroom, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<ClassMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ClassMember::class);
    }

    /**
     * @return HasMany<ClassSchedule, $this>
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(ClassSchedule::class);
    }

    /**
     * @return HasMany<ClassSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }

    protected function casts(): array
    {
        return [
            'mode' => ClassMode::class,
            'capacity' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
