<?php

namespace App\Http\Controllers;

use App\Auth\OneTimePassword;
use App\Auth\RecentCodeLogin;
use App\Rules\IranianMobile;
use App\Support\Identifiers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The user's own account: email, mobile (confirmed by SMS code) and
 * password. Name and national code are official data, changed by staff.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'mobile' => $user->mobile,
                'mobileVerified' => $user->mobile_verified_at !== null,
                'nationalCode' => $user->national_code,
            ],
            'currentPasswordRequired' => ! RecentCodeLogin::isRecent($request),
            'codeLength' => OneTimePassword::LENGTH,
            'resendAfterSeconds' => OneTimePassword::RESEND_AFTER_SECONDS,
        ]);
    }

    public function updateEmail(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($user)],
        ]);

        $user->email = $validated['email'];

        if ($user->email === null && $user->mobile === null && $user->national_code === null) {
            throw ValidationException::withMessages(['email' => 'حساب شما بدون ایمیل، موبایل و کد ملی نمی‌تواند باشد.']);
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('status', 'ایمیل ذخیره شد.');
    }

    /**
     * Sends a confirmation code to the new mobile; it is saved only once the
     * code comes back, so nobody can claim a number they don't hold.
     */
    public function sendMobileCode(Request $request, OneTimePassword $otp): RedirectResponse
    {
        $request->validate(['mobile' => ['required', new IranianMobile]]);

        $user = $request->user();
        $mobile = Identifiers::normalizeMobile($request->string('mobile'));

        if ($mobile === $user->mobile) {
            throw ValidationException::withMessages(['mobile' => 'این همان شماره‌ی فعلی شماست.']);
        }

        if (($wait = $otp->secondsUntilResend($user, OneTimePassword::PURPOSE_CONFIRM_MOBILE, $mobile)) > 0) {
            throw ValidationException::withMessages(['mobile' => "{$wait} ثانیه‌ی دیگر می‌توانید کد جدید بگیرید."]);
        }

        $otp->send($user, OneTimePassword::PURPOSE_CONFIRM_MOBILE, $mobile);

        return back()->with('status', "کد تأیید به {$mobile} پیامک شد.");
    }

    public function updateMobile(Request $request, OneTimePassword $otp): RedirectResponse
    {
        $request->validate([
            'mobile' => ['required', new IranianMobile],
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        $mobile = Identifiers::normalizeMobile($request->string('mobile'));
        $throttleKey = "confirm-mobile:{$user->getKey()}";

        if (RateLimiter::tooManyAttempts($throttleKey, OneTimePassword::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['code' => 'تعداد تلاش‌ها زیاد بود؛ کمی بعد دوباره امتحان کنید.']);
        }

        if (! $otp->verify($user, $request->string('code')->trim()->toString(), OneTimePassword::PURPOSE_CONFIRM_MOBILE, $mobile)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['code' => 'کد وارد شده درست نیست یا منقضی شده است.']);
        }

        RateLimiter::clear($throttleKey);

        $user->forceFill(['mobile' => $mobile, 'mobile_verified_at' => now()])->save();

        return back()->with('status', 'شماره موبایل تغییر کرد.');
    }

    /**
     * Right after an SMS-code login the current password is not asked for:
     * that is how a forgotten password is reset. Other sessions of the user
     * are logged out (the `auth.session` middleware notices the new hash).
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => [Rule::requiredIf(! RecentCodeLogin::isRecent($request)), 'nullable', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        // The hashed cast hashes it; AuthenticateSession stores the new hash
        // in this session and logs out sessions that still carry the old one.
        $request->user()->forceFill(['password' => $validated['password']])->save();
        RecentCodeLogin::forget($request);

        return back()->with('status', 'رمز عبور تغییر کرد.');
    }
}
