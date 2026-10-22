<?php

namespace App\Auth;

use Illuminate\Http\Request;

/**
 * Remembers in the session that the user just logged in with an SMS code.
 * For a short while they may set a new password without the current one:
 * that is the password reset path (ADR 0004).
 */
class RecentCodeLogin
{
    public const SESSION_KEY = 'auth.code_login_at';

    public const VALID_SECONDS = 15 * 60;

    public static function mark(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, now()->getTimestamp());
    }

    public static function isRecent(Request $request): bool
    {
        $at = $request->session()->get(self::SESSION_KEY);

        return $at !== null && now()->getTimestamp() - $at <= self::VALID_SECONDS;
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
