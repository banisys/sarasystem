<?php

namespace Modules\Tutorial\Support;

use App\Models\User;
use App\Support\Identifiers;

/**
 * Persian names and texts for sample data (ADR 0046). Not Faker: it is a
 * dev dependency, and installations run without it.
 */
class SampleTexts
{
    public const array MALE_NAMES = ['علی', 'محمد', 'حسین', 'رضا', 'امیر', 'مهدی', 'سینا', 'آرش', 'کیان', 'پارسا', 'بردیا', 'نیما', 'سامان', 'یاسین', 'ماهان', 'آرمان', 'بهراد', 'سهیل', 'کامران', 'فرهاد'];

    public const array FEMALE_NAMES = ['زهرا', 'فاطمه', 'مریم', 'سارا', 'نرگس', 'یاسمن', 'هستی', 'نازنین', 'ریحانه', 'الناز', 'مهسا', 'ترانه', 'آیدا', 'پریا', 'شیوا', 'رها', 'نگار', 'بهار', 'ستاره', 'مینا'];

    public const array LAST_NAMES = ['احمدی', 'محمدی', 'حسینی', 'رضایی', 'کریمی', 'موسوی', 'جعفری', 'صادقی', 'رحیمی', 'نوری', 'کاظمی', 'مرادی', 'قاسمی', 'عباسی', 'یوسفی', 'اکبری', 'شریفی', 'زارعی', 'طاهری', 'فرهادی', 'سلطانی', 'باقری', 'ملکی', 'نجفی', 'افشار'];

    public const array SUBJECTS = ['ریاضی', 'علوم', 'ادبیات فارسی', 'زبان انگلیسی', 'مطالعات اجتماعی'];

    public const array SUMMARIES = [
        'مرور جلسه‌ی قبل و حل تمرین‌های کتاب.',
        'آموزش مفهوم تازه با مثال و پرسش و پاسخ.',
        'کار گروهی و ارائه‌ی کوتاه هر گروه.',
        'آزمونک کلاسی و رفع اشکال.',
        'حل مسئله‌های چالشی و جمع‌بندی فصل.',
    ];

    public const array HOMEWORK = [
        'تمرین‌های پایان درس را حل کنید.',
        'خلاصه‌ی درس را در یک صفحه بنویسید.',
        'سه مثال تازه برای مفهوم این جلسه پیدا کنید.',
        'پرسش‌های صفحه‌ی بعد را پاسخ دهید.',
    ];

    public const array ANSWERS = [
        'پاسخ تمرین‌ها پیوست است.',
        'همه‌ی پرسش‌ها را پاسخ دادم؛ پرسش آخر را مطمئن نیستم.',
        'خلاصه را نوشتم و مثال‌ها را اضافه کردم.',
    ];

    public const array FEEDBACK = ['عالی بود.', 'خوب بود؛ پرسش آخر را دوباره ببین.', 'دقیق‌تر بنویس.', 'آفرین، پیشرفت خوبی داشتی.'];

    public static function pick(array $values): mixed
    {
        return $values[array_rand($values)];
    }

    /**
     * @return array{string, string} first name, last name
     */
    public static function person(?bool $male = null): array
    {
        $male ??= (bool) random_int(0, 1);

        return [self::pick($male ? self::MALE_NAMES : self::FEMALE_NAMES), self::pick(self::LAST_NAMES)];
    }

    /**
     * A valid Iranian national code that no user has yet.
     */
    public static function nationalCode(): string
    {
        do {
            $digits = str_pad((string) random_int(1, 999_999_999), 9, '0', STR_PAD_LEFT);
            $sum = 0;
            for ($i = 0; $i < 9; $i++) {
                $sum += (int) $digits[$i] * (10 - $i);
            }
            $remainder = $sum % 11;
            $code = $digits.($remainder < 2 ? $remainder : 11 - $remainder);
        } while (! Identifiers::isValidNationalCode($code) || User::query()->where('national_code', $code)->exists());

        return $code;
    }
}
