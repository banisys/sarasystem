<?php

namespace Modules\Class\Services\Online;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Class\Models\ClassOnlineRoom;
use Modules\Class\Models\OnlineServer;
use Modules\Class\Models\OnlineServerAccount;

/**
 * Adobe Connect (ADR 0073), after the old sarayedanesh system: a meeting
 * per room of a class, made the first time someone enters, closed to
 * guests; an account per person («{prefix}{user id}@{host}») made the
 * first time they enter, with a password worked out from the app key; the
 * person is given host or participant access to the meeting each time they
 * enter, logged in, and sent to the meeting with their session.
 */
class AdobeConnectDriver implements OnlinePlatformDriver
{
    public function enter(ClassOnlineRoom $room, User $user, bool $asHost): string
    {
        $server = $room->server;

        if ($server === null || ! $server->is_active) {
            throw new OnlineClassException('سرور کلاس آنلاین این کلاس غیرفعال است؛ به مدیر کلاس خبر دهید.');
        }

        $api = new AdobeConnectApi($server);
        $room = $this->meetingOf($api, $room);
        $principalId = $this->accountOf($api, $server, $user);

        try {
            $this->allow($api, $room, $principalId, $asHost);
        } catch (AdobeConnectError $error) {
            if (! in_array($error->status, ['no-data', 'invalid'], true)) {
                throw $error;
            }

            // The account was deleted on the server: make it again.
            OnlineServerAccount::query()->where('server_id', $server->getKey())->where('user_id', $user->getKey())->delete();
            $this->allow($api, $room, $principalId = $this->accountOf($api, $server, $user), $asHost);
        }

        try {
            $session = $api->login($this->login($server, $user), $this->password($server, $user));
        } catch (AdobeConnectError $error) {
            if ($error->status === 'unreachable') {
                throw $error;
            }

            // The password on the server is another (the app key changed,
            // or someone set it there): set ours again.
            $this->setPassword($api, $principalId, $this->password($server, $user));
            $session = $api->login($this->login($server, $user), $this->password($server, $user));
        }

        return $server->baseUrl().'/'.trim((string) $room->url_path, '/').'/?session='.urlencode($session);
    }

    public function problemWith(OnlineServer $server): ?string
    {
        try {
            $api = new AdobeConnectApi($server);
            $api->call('common-info');
            $this->folderId($api, $server);

            return null;
        } catch (OnlineClassException $exception) {
            return $exception->getMessage();
        }
    }

    /**
     * «sara12@adobe.example.com»: the server's prefix keeps them apart from
     * accounts other systems made on the same server.
     */
    public function login(OnlineServer $server, User $user): string
    {
        $host = parse_url($server->baseUrl(), PHP_URL_HOST) ?: 'sarasystem.local';

        return $server->setting('login_prefix', 'sara').$user->getKey().'@'.$host;
    }

    /**
     * Not stored anywhere: the same for the person on the server as long as
     * the app key stays.
     */
    public function password(OnlineServer $server, User $user): string
    {
        return 'Sa#'.substr(hash_hmac('sha256', 'adobe-connect|'.$server->getKey().'|'.$user->getKey(), (string) config('app.key')), 0, 24).'9';
    }

    /**
     * The room's meeting, made on the first entry (one entry at a time).
     */
    private function meetingOf(AdobeConnectApi $api, ClassOnlineRoom $room): ClassOnlineRoom
    {
        if ($room->external_id !== null) {
            return $room;
        }

        return DB::transaction(function () use ($api, $room): ClassOnlineRoom {
            $room = ClassOnlineRoom::withoutTenantScope()->with(['classroom' => fn ($query) => $query->withoutGlobalScopes(), 'server'])->lockForUpdate()->findOrFail($room->getKey());

            if ($room->external_id !== null) {
                return $room;
            }

            $classroom = $room->classroom;
            $name = Str::limit(trim($classroom->title.($classroom->code ? " ({$classroom->code})" : '')), 200, '').' #'.$room->getKey();

            $sco = $api->call('sco-update', [
                'type' => 'meeting',
                'name' => $name,
                'folder-id' => $this->folderId($api, $room->server),
                'description' => 'سرا سیستم — کلاس '.$classroom->getKey(),
            ])->sco;

            // Only accounts given access enter; no guests.
            $api->call('permissions-update', ['acl-id' => (string) $sco['sco-id'], 'principal-id' => 'public-access', 'permission-id' => 'denied']);

            $room->update(['external_id' => (string) $sco['sco-id'], 'url_path' => (string) $sco->{'url-path'}]);

            return $room;
        });
    }

    /**
     * The folder rooms are made in: the server's setting, or its «Shared
     * Meetings».
     */
    private function folderId(AdobeConnectApi $api, OnlineServer $server): string
    {
        if (filled($folder = $server->setting('folder_id'))) {
            return (string) $folder;
        }

        foreach ($api->call('sco-shortcuts')->shortcuts->sco ?? [] as $shortcut) {
            if ((string) $shortcut['type'] === 'meetings') {
                return (string) $shortcut['sco-id'];
            }
        }

        throw new OnlineClassException('پوشه‌ی جلسه‌ها در سرور Adobe Connect پیدا نشد؛ شناسه‌ی پوشه را در تنظیمات سرور وارد کنید.');
    }

    /**
     * The person's principal-id, making the account (or taking over one
     * with the same login) the first time.
     */
    private function accountOf(AdobeConnectApi $api, OnlineServer $server, User $user): string
    {
        $account = OnlineServerAccount::query()->where('server_id', $server->getKey())->where('user_id', $user->getKey())->first();

        if ($account !== null) {
            return $account->external_id;
        }

        $login = $this->login($server, $user);
        $existing = $api->call('principal-list', ['filter-login' => $login])->{'principal-list'}->principal ?? null;

        if ($existing !== null && (string) $existing['principal-id'] !== '') {
            $principalId = (string) $existing['principal-id'];
            $this->setPassword($api, $principalId, $this->password($server, $user));
        } else {
            [$first, $last] = $this->nameParts($user);
            $principalId = (string) $api->call('principal-update', [
                'type' => 'user',
                'has-children' => 0,
                'first-name' => $first,
                'last-name' => $last,
                'login' => $login,
                'email' => $login,
                'password' => $this->password($server, $user),
                'send-email' => 'false',
            ])->principal['principal-id'];
        }

        OnlineServerAccount::query()->updateOrCreate(['server_id' => $server->getKey(), 'user_id' => $user->getKey()], ['external_id' => $principalId]);

        return $principalId;
    }

    private function allow(AdobeConnectApi $api, ClassOnlineRoom $room, string $principalId, bool $asHost): void
    {
        $api->call('permissions-update', ['acl-id' => $room->external_id, 'principal-id' => $principalId, 'permission-id' => $asHost ? 'host' : 'view']);
    }

    private function setPassword(AdobeConnectApi $api, string $principalId, string $password): void
    {
        $api->call('user-update-pwd', ['user-id' => $principalId, 'password' => $password, 'password-verify' => $password]);
    }

    /**
     * Adobe wants a first and a last name; «مریم احمدی» → «مریم», «احمدی».
     *
     * @return array{0: string, 1: string}
     */
    private function nameParts(User $user): array
    {
        $parts = preg_split('/\s+/u', trim((string) $user->name), 2) ?: [];

        return [$parts[0] ?? 'کاربر', $parts[1] ?? '-'];
    }
}
