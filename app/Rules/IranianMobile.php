<?php

namespace App\Rules;

use App\Support\Identifiers;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IranianMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Identifiers::normalizeMobile((string) $value) === null) {
            $fail('شماره موبایل معتبر نیست.');
        }
    }
}
