<?php

namespace App\Auth;

use App\Models\User;
use App\Sms\SmsSender;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Hash;

/**
 * SMS login codes (ADR 0004): 6 digits, stored hashed in the cache, valid
 * for 2 minutes, 5 wrong tries, and a new code at most once a minute.
 */
class OneTimePassword
{
    public const LENGTH = 6;

    public const TTL_SECONDS = 120;

    public const RESEND_AFTER_SECONDS = 60;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private Cache $cache, private SmsSender $sms) {}

    /**
     * Seconds until a new code may be sent to the user; 0 when it may be sent now.
     */
    public function secondsUntilResend(User $user): int
    {
        $sentAt = $this->cache->get($this->key($user))['sent_at'] ?? null;

        return $sentAt === null ? 0 : max(0, $sentAt + self::RESEND_AFTER_SECONDS - now()->getTimestamp());
    }

    public function send(User $user): void
    {
        $code = str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);

        $this->cache->put($this->key($user), [
            'hash' => Hash::make($code),
            'sent_at' => now()->getTimestamp(),
            'attempts' => 0,
        ], self::TTL_SECONDS);

        $this->sms->send($user->mobile, 'کد ورود شما به '.config('app.name').": {$code}");
    }

    /**
     * Checks the code; a correct code is consumed, and too many wrong
     * tries throw the code away.
     */
    public function verify(User $user, string $code): bool
    {
        $key = $this->key($user);
        $entry = $this->cache->get($key);

        if ($entry === null) {
            return false;
        }

        if (Hash::check($code, $entry['hash'])) {
            $this->cache->forget($key);

            return true;
        }

        $entry['attempts']++;

        if ($entry['attempts'] >= self::MAX_ATTEMPTS) {
            $this->cache->forget($key);
        } else {
            $this->cache->put($key, $entry, max(1, $entry['sent_at'] + self::TTL_SECONDS - now()->getTimestamp()));
        }

        return false;
    }

    private function key(User $user): string
    {
        return "otp:login:{$user->getKey()}";
    }
}
