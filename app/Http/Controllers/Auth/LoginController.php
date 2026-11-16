<?php

namespace App\Http\Controllers\Auth;

use App\Auth\OneTimePassword;
use App\Auth\RecentCodeLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\OtpLoginRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single login page for everyone (ADR 0004): password or SMS code,
 * with a mobile, national code or email as identifier.
 */
class LoginController extends Controller
{
    public function create(): InertiaResponse
    {
        return Inertia::render('Auth/Login', [
            'codeLength' => OneTimePassword::LENGTH,
            'resendAfterSeconds' => OneTimePassword::RESEND_AFTER_SECONDS,
        ]);
    }

    public function store(LoginRequest $request): Response
    {
        return $this->logIn($request, $request->authenticatedUser());
    }

    public function storeWithCode(OtpLoginRequest $request, OneTimePassword $otp): Response
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

    private function logIn(Request $request, User $user): Response
    {
        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        $panel = Filament::getPanel('admin');
        $adminUrl = route('filament.admin.tenant');
        $canOpenAdmin = $user->canAccessPanel($panel) && $user->getTenants($panel)->isNotEmpty();
        $response = redirect()->intended($canOpenAdmin ? $adminUrl : route('dashboard'));

        $adminPath = parse_url($adminUrl, PHP_URL_PATH);
        $targetPath = (string) parse_url($response->getTargetUrl(), PHP_URL_PATH);

        if ($targetPath === $adminPath || str_starts_with($targetPath, $adminPath.'/')) {
            return Inertia::location($response);
        }

        return $response;
    }
}
