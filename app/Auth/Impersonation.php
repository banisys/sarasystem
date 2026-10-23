<?php

namespace App\Auth;

use App\Http\Middleware\ResolveCurrentTenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * A super admin logs in as another user to see the system through their eyes,
 * and can go back to their own account at any time (ADR 0008). The original
 * user's id is kept in the session for the whole impersonation.
 */
class Impersonation
{
    public const SESSION_KEY = 'auth.impersonator_id';

    public const RETURN_URL_SESSION_KEY = 'auth.impersonator_return_url';

    public static function canImpersonate(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin()
            && ! $actor->is($target)
            && ! $target->isSuperAdmin()
            && ! self::isActive();
    }

    public static function isActive(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public static function impersonator(): ?User
    {
        return self::isActive() ? User::find(session(self::SESSION_KEY)) : null;
    }

    /**
     * Log in as the target; $returnUrl is where leaving brings the actor back.
     */
    public static function start(User $actor, User $target, ?string $returnUrl = null): void
    {
        abort_unless(self::canImpersonate($actor, $target), 403);

        self::switchTo($target);

        session()->put([
            self::SESSION_KEY => $actor->getKey(),
            self::RETURN_URL_SESSION_KEY => $returnUrl,
        ]);

        Log::info('Impersonation started', ['impersonator_id' => $actor->getKey(), 'user_id' => $target->getKey()]);
    }

    /**
     * Log back in as the original user. Returns where to send them.
     */
    public static function stop(): string
    {
        $impersonator = self::impersonator();
        $returnUrl = session(self::RETURN_URL_SESSION_KEY);
        session()->forget([self::SESSION_KEY, self::RETURN_URL_SESSION_KEY]);

        if ($impersonator === null) {
            Auth::guard('web')->logout();
            session()->invalidate();
            session()->regenerateToken();

            return route('login');
        }

        Log::info('Impersonation ended', ['impersonator_id' => $impersonator->getKey(), 'user_id' => Auth::id()]);

        self::switchTo($impersonator);

        return $returnUrl ?? '/cpadmin';
    }

    /**
     * Swap the logged-in user. State that belongs to the previous user (the
     * chosen tenant, the password-reset window of an SMS login) is dropped,
     * and the session's password hash is updated so auth.session does not
     * log the new user out.
     */
    private static function switchTo(User $user): void
    {
        $guard = Auth::guard('web');
        $guard->login($user);

        $session = session();
        $session->forget([ResolveCurrentTenant::SESSION_KEY, RecentCodeLogin::SESSION_KEY]);

        if ($user->getAuthPassword()) {
            $session->put('password_hash_web', $guard->hashPasswordForCookie($user->getAuthPassword()));
        } else {
            $session->forget('password_hash_web');
        }
    }
}
