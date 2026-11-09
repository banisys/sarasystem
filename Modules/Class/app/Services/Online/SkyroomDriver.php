<?php

namespace Modules\Class\Services\Online;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Class\Models\ClassOnlineRoom;
use Modules\Class\Models\ClassSession;
use Modules\Class\Models\OnlineServer;

/**
 * A persistent room per class, entered through a short-lived login URL.
 * Skyroom's createLoginUrl supplies the identity and role without making accounts.
 */
class SkyroomDriver implements OnlinePlatformDriver
{
    public function enter(ClassOnlineRoom $room, ClassSession $session, User $user, bool $asHost): string
    {
        $server = $room->server;

        if ($server === null || ! $server->is_active) {
            throw new OnlineClassException('سرور کلاس آنلاین این کلاس غیرفعال است؛ به مدیر کلاس خبر دهید.');
        }

        $api = new SkyroomApi($server);
        $roomId = $this->roomOf($api, $room, $server);
        $url = $api->call('createLoginUrl', [
            'room_id' => (int) $roomId,
            'user_id' => 'sara'.$user->getKey(),
            'nickname' => $user->name,
            'access' => $asHost ? 3 : 1,
            'concurrent' => $asHost ? (int) $server->setting('host_concurrent', 3) : 1,
            'language' => 'fa',
            'ttl' => 300,
        ]);

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true)) {
            throw new OnlineClassException('لینک ورود دریافتی از Skyroom معتبر نیست.');
        }

        return $url;
    }

    public function problemWith(OnlineServer $server): ?string
    {
        try {
            (new SkyroomApi($server))->call('getServices');

            return null;
        } catch (OnlineClassException $exception) {
            return $exception->getMessage();
        }
    }

    private function roomOf(SkyroomApi $api, ClassOnlineRoom $room, OnlineServer $server): string
    {
        return DB::transaction(function () use ($api, $room, $server): string {
            $locked = ClassOnlineRoom::query()->lockForUpdate()->findOrFail($room->getKey());

            if (filled($locked->external_id)) {
                return $locked->external_id;
            }

            $classroom = $locked->classroom;
            $id = $api->call('createRoom', [
                'name' => 'sara-'.substr(hash('sha256', config('app.url')), 0, 10).'-'.$locked->getKey(),
                'title' => Str::limit($classroom->title.' ('.$classroom->code.') #'.$locked->getKey(), 128, ''),
                'guest_login' => false,
                'op_login_first' => true,
                'max_users' => (int) ($classroom->capacity ?: $server->setting('max_users', 50)),
            ]);

            if (! is_numeric($id) || (int) $id < 1) {
                throw new OnlineClassException('شناسه‌ی اتاق دریافتی از Skyroom معتبر نیست.');
            }

            $locked->update(['external_id' => (string) $id]);

            return (string) $id;
        });
    }
}
