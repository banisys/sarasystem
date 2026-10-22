<?php

namespace App\Http\Requests\Auth;

use App\Auth\OneTimePassword;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class OtpLoginRequest extends FormRequest
{
    use ResolvesLoginUser;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * The user whose SMS code matches; failed attempts count towards the lockout.
     */
    public function authenticatedUser(OneTimePassword $otp): User
    {
        $this->ensureIsNotRateLimited();

        $user = $this->loginUser();

        if ($user === null || ! $otp->verify($user, $this->string('code')->trim()->toString())) {
            $this->failLogin('کد وارد شده درست نیست یا منقضی شده است.');
        }

        $this->clearRateLimiter();

        return $user;
    }
}
