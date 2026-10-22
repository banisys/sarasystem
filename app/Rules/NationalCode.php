<?php

namespace App\Rules;

use App\Support\Identifiers;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NationalCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Identifiers::isValidNationalCode((string) $value)) {
            $fail('کد ملی معتبر نیست.');
        }
    }
}
