<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class LoginRequest extends FormRequest
{
    use ResolvesLoginUser;

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ];
    }

    /**
     * The user whose password matches; failed attempts count towards the lockout.
     */
    public function authenticatedUser(): User
    {
        $this->ensureIsNotRateLimited();

        $user = $this->loginUser();

        if ($user === null || ! Hash::check($this->string('password'), $user->password)) {
            $this->failLogin();
        }

        $this->clearRateLimiter();

        return $user;
    }
}
