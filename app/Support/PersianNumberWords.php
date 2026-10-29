<?php

namespace App\Support;

/**
 * Whole numbers in Persian words, e.g. 1250000 → «یک میلیون و دویست و
 * پنجاه هزار», for amounts written out in contracts (ADR 0020).
 */
final class PersianNumberWords
{
    private const ONES = ['', 'یک', 'دو', 'سه', 'چهار', 'پنج', 'شش', 'هفت', 'هشت', 'نه'];

    private const TEENS = ['ده', 'یازده', 'دوازده', 'سیزده', 'چهارده', 'پانزده', 'شانزده', 'هفده', 'هجده', 'نوزده'];

    private const TENS = ['', '', 'بیست', 'سی', 'چهل', 'پنجاه', 'شصت', 'هفتاد', 'هشتاد', 'نود'];

    private const HUNDREDS = ['', 'صد', 'دویست', 'سیصد', 'چهارصد', 'پانصد', 'ششصد', 'هفتصد', 'هشتصد', 'نهصد'];

    private const SCALES = ['', 'هزار', 'میلیون', 'میلیارد', 'هزار میلیارد'];

    public static function convert(int $number): string
    {
        if ($number === 0) {
            return 'صفر';
        }

        if ($number < 0) {
            return 'منفی '.self::convert(-$number);
        }

        $parts = [];

        foreach (array_reverse(str_split(strrev((string) $number), 3), true) as $scale => $digits) {
            $group = (int) strrev($digits);

            if ($group !== 0) {
                $parts[] = trim(self::belowThousand($group).' '.(self::SCALES[$scale] ?? ''));
            }
        }

        return implode(' و ', $parts);
    }

    private static function belowThousand(int $number): string
    {
        $parts = [];

        if ($number >= 100) {
            $parts[] = self::HUNDREDS[intdiv($number, 100)];
            $number %= 100;
        }

        if ($number >= 20) {
            $parts[] = self::TENS[intdiv($number, 10)];
            $number %= 10;
        } elseif ($number >= 10) {
            $parts[] = self::TEENS[$number - 10];
            $number = 0;
        }

        if ($number > 0) {
            $parts[] = self::ONES[$number];
        }

        return implode(' و ', $parts);
    }
}
