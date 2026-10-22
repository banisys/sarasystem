<?php

namespace App\Support;

/**
 * Normalizing and checking Iranian identifiers (mobile, national code).
 */
class Identifiers
{
    /**
     * Persian (۰-۹) and Arabic (٠-٩) digits to Latin digits.
     */
    public static function toLatinDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /**
     * An Iranian mobile in the form 09xxxxxxxxx, from 09…, 9…, 989… or +989…;
     * null when the value is not a mobile number.
     */
    public static function normalizeMobile(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[\s\-()]/', '', static::toLatinDigits(trim($value)));
        $digits = preg_replace('/^(\+98|0098|98)(?=9\d{9}$)/', '', $digits);
        $digits = preg_replace('/^(?=9\d{9}$)/', '0', $digits);

        return preg_match('/^09\d{9}$/', $digits) ? $digits : null;
    }

    /**
     * Iranian national code (کد ملی): 10 digits with a check digit.
     */
    public static function isValidNationalCode(?string $value): bool
    {
        $code = static::toLatinDigits(trim((string) $value));

        if (! preg_match('/^\d{10}$/', $code) || preg_match('/^(\d)\1{9}$/', $code)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $code[$i] * (10 - $i);
        }

        $remainder = $sum % 11;
        $check = (int) $code[9];

        return $remainder < 2 ? $check === $remainder : $check === 11 - $remainder;
    }
}
