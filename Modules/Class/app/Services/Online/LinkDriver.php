<?php

namespace Modules\Class\Services\Online;

use App\Models\User;
use Modules\Class\Models\ClassOnlineRoom;
use Modules\Class\Models\OnlineServer;

/**
 * A meeting address on any platform (Google Meet, Skyroom, …): everyone is
 * sent to it as it is (ADR 0073).
 */
class LinkDriver implements OnlinePlatformDriver
{
    public function enter(ClassOnlineRoom $room, User $user, bool $asHost): string
    {
        if (blank($room->meeting_url)) {
            throw new OnlineClassException('لینک کلاس آنلاین هنوز ثبت نشده است.');
        }

        return $room->meeting_url;
    }

    public function problemWith(OnlineServer $server): ?string
    {
        return null;
    }
}
