<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Class\Database\Factories\ClassScheduleFactory;
use Modules\Class\Enums\Frequency;
use Modules\Class\Enums\Weekday;

/**
 * When a class meets (ADR 0023): a rule that repeats once, daily, weekly or
 * monthly (Jalali months) between two dates, at a time, optionally for a
 * subject with a teacher and in another place than the class's. Its
 * sessions are built when it is saved (SessionPlanner).
 *
 * @property list<int> $weekdays
 */
#[Fillable([
    'tenant_id', 'classroom_id', 'subject_id', 'teacher_id', 'frequency', 'interval', 'weekdays', 'month_day', 'month_week',
    'starts_on', 'ends_on', 'start_time', 'end_time', 'location',
])]
class ClassSchedule extends Model
{
    /** @use HasFactory<ClassScheduleFactory> */
    use BelongsToTenant, HasFactory;

    protected static function newFactory(): ClassScheduleFactory
    {
        return ClassScheduleFactory::new();
    }

    /**
     * E.g. «هر ۲ هفته: شنبه، دوشنبه · 08:00 تا 09:30».
     */
    public function summary(): string
    {
        $days = implode('، ', array_map(fn (Weekday $day): string => $day->getLabel(), $this->sortedWeekdays()));
        $every = $this->interval > 1 ? "هر {$this->interval} {$this->frequency->unit()}" : $this->frequency->getLabel();

        $when = match ($this->frequency) {
            Frequency::Once => 'یک بار',
            Frequency::Daily => $every.($days !== '' && count($this->weekdays) < 7 ? " ({$days})" : ''),
            Frequency::Weekly => "{$every}: {$days}",
            Frequency::Monthly => $this->month_week !== null
                ? "{$every}، ".($this->month_week === -1 ? 'آخرین' : self::ordinal($this->month_week))." {$days} ماه"
                : "{$every}، روز {$this->month_day} ماه",
        };

        return $when.' · '.substr((string) $this->start_time, 0, 5).' تا '.substr((string) $this->end_time, 0, 5);
    }

    /**
     * @return list<Weekday>
     */
    public function sortedWeekdays(): array
    {
        $days = array_map(fn (int $day): Weekday => Weekday::from($day), $this->weekdays ?? []);
        usort($days, fn (Weekday $a, Weekday $b): int => $a->position() <=> $b->position());

        return $days;
    }

    private static function ordinal(int $number): string
    {
        return [1 => 'اولین', 2 => 'دومین', 3 => 'سومین', 4 => 'چهارمین'][$number] ?? (string) $number;
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Subjects are the primary tenant's.
     *
     * @return BelongsTo<Subject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class)->withoutGlobalScope(TenantScope::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return HasMany<ClassSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class, 'schedule_id');
    }

    protected function casts(): array
    {
        return [
            'frequency' => Frequency::class,
            'interval' => 'integer',
            'weekdays' => 'array',
            'month_day' => 'integer',
            'month_week' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }
}
