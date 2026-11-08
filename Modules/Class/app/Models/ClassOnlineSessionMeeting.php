<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One session's own meeting on a platform, for a room made per session
 * (ADR 0074), e.g. Adobe Connect's sco-id and url-path; made the first time
 * someone enters the session.
 */
#[Fillable(['tenant_id', 'room_id', 'session_id', 'external_id', 'url_path'])]
class ClassOnlineSessionMeeting extends Model
{
    use BelongsToTenant;
}
