<?php

namespace Modules\School\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Class\Models\Classroom;

/**
 * The grade (and major) of a school class (ADR 0031), set in the class form.
 */
#[Fillable(['tenant_id', 'classroom_id', 'grade_id', 'major_id'])]
class ClassroomGrade extends Model
{
    use BelongsToTenant;

    protected $table = 'school_classroom_grades';

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
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
}
