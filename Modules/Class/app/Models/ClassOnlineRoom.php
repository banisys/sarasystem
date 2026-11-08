<?php

namespace Modules\Class\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Class\Enums\OnlinePlatform;

/**
 * Where a class meets online from a day on (ADR 0073): a link, or a room
 * on a platform's server. `starts_on` null is from the beginning; a class
 * that moves to another platform in the middle of its term gets a new room
 * from that day, and its earlier sessions keep the old one. A platform's
 * room is made there the first time someone enters (`external_id`,
 * `url_path`).
 */
#[Fillable(['tenant_id', 'classroom_id', 'platform', 'server_id', 'starts_on', 'meeting_url', 'external_id', 'url_path', 'created_by_id'])]
class ClassOnlineRoom extends Model
{
    use BelongsToTenant;

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return BelongsTo<OnlineServer, $this>
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(OnlineServer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * «link» or «server:3», the value of the class form's choice.
     */
    public function choice(): string
    {
        return $this->platform === OnlinePlatform::Link ? OnlinePlatform::Link->value : 'server:'.$this->server_id;
    }

    /**
     * «Adobe Connect — سرور اول» or «لینک».
     */
    public function platformName(): string
    {
        return $this->server?->displayName() ?? $this->platform->getLabel();
    }

    /**
     * Something to enter: a link with an address, or a platform's room.
     */
    public function isUsable(): bool
    {
        return $this->platform === OnlinePlatform::Link ? filled($this->meeting_url) : $this->server_id !== null;
    }

    protected function casts(): array
    {
        return [
            'platform' => OnlinePlatform::class,
            'starts_on' => 'date',
        ];
    }
}
