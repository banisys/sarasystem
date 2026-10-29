<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Class\Database\Factories\AttendanceFactory;
use Modules\Class\Enums\AttendanceStatus;

/**
 * Whether one attendee was at one session (ADR 0024): present, late (by
 * some minutes), absent or excused, with who recorded it and when.
 */
#[Fillable(['tenant_id', 'classroom_id', 'session_id', 'user_id', 'status', 'late_minutes', 'note', 'recorded_by_id', 'recorded_at'])]
class Attendance extends Model
{
    /** @use HasFactory<AttendanceFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'class_attendances';

    protected static function newFactory(): AttendanceFactory
    {
        return AttendanceFactory::new();
    }

    /**
     * @return BelongsTo<ClassSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    protected function casts(): array
    {
        return [
            'status' => AttendanceStatus::class,
            'late_minutes' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }
}
