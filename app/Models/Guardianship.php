<?php

namespace App\Models;

use App\Guardians\GuardianRelationship;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user who is another user's parent or guardian (ADR 0027), e.g. the
 * father of a pupil. Between users, not tenants: a child is the same child
 * in every branch. The guardian sees what modules show of the ward (their
 * classes, attendance, homework) and may get SMS about them. A ward
 * without a mobile of their own may sign in with one guardian's mobile,
 * kept in step with it (ADR 0028).
 */
#[Fillable(['guardian_id', 'ward_id', 'relationship', 'receives_sms', 'provides_login_mobile', 'created_by_id'])]
class Guardianship extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function ward(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ward_id');
    }

    protected function casts(): array
    {
        return [
            'relationship' => GuardianRelationship::class,
            'receives_sms' => 'boolean',
            'provides_login_mobile' => 'boolean',
        ];
    }
}
