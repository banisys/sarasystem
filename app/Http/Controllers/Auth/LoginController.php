<?php

namespace App\Http\Controllers\Auth;

use App\Auth\OneTimePassword;
use App\Auth\RecentCodeLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OtpLoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The single login page for everyone (ADR 0004): password or SMS code,
 * with a mobile, national code or email as identifier.
 */
class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'codeLength' => OneTimePassword::LENGTH,
            'resendAfterSeconds' => OneTimePassword::RESEND_AFTER_SECONDS,
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        return $this->logIn($request, $request->authenticatedUser());
    }

    public function storeWithCode(OtpLoginRequest $request, OneTimePassword $otp): RedirectResponse
    {
        $user = $request->authenticatedUser($otp);

        // The code reached this mobile, so the user controls it.
        $user->forceFill(['mobile_verified_at' => now()])->save();

        $response = $this->logIn($request, $user);
        RecentCodeLogin::mark($request);

        return $response;
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function logIn(Request $request, User $user): RedirectResponse
    {
        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
