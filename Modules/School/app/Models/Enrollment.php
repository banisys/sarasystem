<?php

namespace Modules\School\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Class\Models\Classroom;
use Modules\Class\Models\Term;

/**
 * A learner's grade (and major and main class) in an academic year of a
 * tenant («پرونده‌ی تحصیلی», ADR 0031). Filled in when they join a class
 * that has a grade; made for the next year by promotion.
 */
#[Fillable(['tenant_id', 'user_id', 'term_id', 'grade_id', 'major_id', 'classroom_id'])]
class Enrollment extends Model
{
    use BelongsToTenant;

    protected $table = 'school_enrollments';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Terms are the primary tenant's.
     *
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Major, $this>
     */
    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
