<?php

namespace Modules\Class\Services\Online;

use App\Models\User;
use Modules\Class\Models\ClassOnlineRoom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\OnlineServer;

/**
 * What the system does with a platform of online classes (ADR 0073). A
 * new platform (BigBlueButton, …) is a case of OnlinePlatform and
 * a driver.
 */
interface OnlinePlatformDriver
{
    /**
     * The address that takes the person into the session's room, as its host
     * (teaching, presenting) or as a participant; making the room and the
     * person's account on the platform first if needed.
     *
     * @throws OnlineClassException
     */
    public function enter(ClassOnlineRoom $room, ClassSession $session, User $user, bool $asHost): string;

    /**
     * Why the system can't work with the server, or null when it can.
     */
    public function problemWith(OnlineServer $server): ?string;
}
