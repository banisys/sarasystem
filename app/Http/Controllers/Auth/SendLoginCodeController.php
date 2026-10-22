<?php

namespace App\Http\Controllers\Auth;

use App\Auth\OneTimePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendLoginCodeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class SendLoginCodeController extends Controller
{
    /**
     * Sends an SMS login code. For an unknown identifier the answer looks the
     * same as a successful send, so it does not reveal who has an account.
     */
    public function __invoke(SendLoginCodeRequest $request, OneTimePassword $otp): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $user = $request->loginUser();
        $sent = back()->with('status', 'اگر حسابی با این مشخصات وجود داشته باشد، کد ورود پیامک شد.');

        if ($user === null) {
            $request->hitRateLimiter();

            return $sent;
        }

        if ($user->mobile === null) {
            throw ValidationException::withMessages(['identifier' => 'برای این حساب شماره موبایل ثبت نشده است؛ با رمز عبور وارد شوید.']);
        }

        if (($wait = $otp->secondsUntilResend($user)) > 0) {
            throw ValidationException::withMessages(['identifier' => "کد قبلی هنوز معتبر است؛ {$wait} ثانیه‌ی دیگر می‌توانید کد جدید بگیرید."]);
        }

        $otp->send($user);

        return $sent;
    }
}
