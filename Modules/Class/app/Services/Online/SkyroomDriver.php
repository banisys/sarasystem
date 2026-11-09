<?php

namespace Modules\Class\Services\Online;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
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
        $params = [
            'room_id' => (int) $roomId,
            'user_id' => 'sara'.$user->getKey(),
            'nickname' => Str::limit($user->name, 128, ''),
            'access' => $asHost ? 3 : 1,
            'concurrent' => $asHost ? (int) $server->setting('host_concurrent', 3) : 1,
            'language' => 'fa',
            'ttl' => 300,
        ];

        $key = 'class-online:skyroom:login:'.hash('sha256', json_encode([
            $room->tenant_id, $room->getKey(), $session->getKey(), $server->getKey(),
            $server->baseUrl(), $server->password, $params,
        ], JSON_THROW_ON_ERROR));

        if (is_string($cached = Cache::get($key))) {
            return $cached;
        }

        $url = Cache::lock($key.':lock', 30)->get(fn (): string => Cache::remember(
            $key, 240, fn (): string => $this->createLoginUrl($api, $params),
        ));

        if ($url === false) {
            throw new OnlineClassException('لینک ورود Skyroom در حال آماده‌سازی است؛ چند لحظه دیگر دوباره تلاش کنید.');
        }

        return $url;
    }

    /**
     * @param  array<string, int|string>  $params
     */
    private function createLoginUrl(SkyroomApi $api, array $params): string
    {
        $url = $api->call('createLoginUrl', $params);

        if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['https', 'http'], true)) {
            throw new OnlineClassException('لینک ورود دریافتی از Skyroom معتبر نیست.');
        }

        return $url;
    }

    public function problemWith(OnlineServer $server): ?string
    {
        try {
            $services = (new SkyroomApi($server))->call('getServices');

            if (! is_array($services) || ! array_is_list($services)) {
                return 'فهرست سرویس‌های دریافتی از Skyroom معتبر نیست.';
            }

            $active = collect($services)->filter(fn ($service): bool => is_array($service) && (int) ($service['status'] ?? 0) === 1);
            $serviceId = $server->setting('service_id');

            if ($serviceId !== null) {
                return $active->contains(fn (array $service): bool => (string) ($service['id'] ?? '') === (string) $serviceId)
                    ? null : 'سرویس انتخاب‌شده‌ی Skyroom پیدا نشد یا فعال نیست.';
            }

            return match ($active->count()) {
                0 => 'هیچ سرویس فعالی در Skyroom ندارید.',
                1 => null,
                default => 'چند سرویس فعال در Skyroom دارید؛ شناسه‌ی سرویس را در تنظیمات سرور وارد کنید.',
            };
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
                ...($server->setting('service_id') !== null ? ['service_id' => (int) $server->setting('service_id')] : []),
            ]);

            if (! is_numeric($id) || (int) $id < 1) {
                throw new OnlineClassException('شناسه‌ی اتاق دریافتی از Skyroom معتبر نیست.');
            }

            $locked->update(['external_id' => (string) $id]);

            return (string) $id;
        });
    }
}
