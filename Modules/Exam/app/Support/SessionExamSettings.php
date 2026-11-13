<?php

namespace Modules\Exam\Support;

use Illuminate\Validation\Rule;
use Modules\Exam\Enums\ResultVisibility;
use Modules\Exam\Enums\RetakePolicy;

class SessionExamSettings
{
    public const array DEFAULTS = [
        'title' => '',
        'description' => null,
        'duration_minutes' => 30,
        'grace_minutes' => 3,
        'total_score' => 20,
        'passing_score' => 12,
        'questions_per_page' => 5,
        'negative_marking' => null,
        'max_attempts' => 1,
        'retake_policy' => RetakePolicy::Always->value,
        'result_visibility' => ResultVisibility::Hidden->value,
        'shuffle_questions' => false,
        'shuffle_options' => false,
    ];

    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'duration_minutes' => ['required', 'integer', 'between:1,1440'],
            'grace_minutes' => ['sometimes', 'required', 'integer', 'between:0,60'],
            'total_score' => ['required', 'numeric', 'between:1,1000'],
            'passing_score' => ['required', 'numeric', 'min:0', 'lte:total_score'],
            'questions_per_page' => ['sometimes', 'required', 'integer', 'between:1,100'],
            'negative_marking' => ['nullable', 'integer', Rule::in([2, 3, 4, 5, 6])],
            'max_attempts' => ['sometimes', 'required', 'integer', 'between:1,100'],
            'retake_policy' => ['sometimes', 'required', Rule::enum(RetakePolicy::class)],
            'result_visibility' => ['sometimes', 'required', Rule::enum(ResultVisibility::class)],
            'shuffle_questions' => ['sometimes', 'required', 'boolean'],
            'shuffle_options' => ['sometimes', 'required', 'boolean'],
        ];
    }

    public static function labels(): array
    {
        return [
            'title' => 'عنوان آزمون',
            'description' => 'توضیحات',
            'duration_minutes' => 'مدت آزمون (دقیقه)',
            'grace_minutes' => 'زمان ارفاق (دقیقه)',
            'total_score' => 'نمرهٔ کل',
            'passing_score' => 'نمرهٔ قبولی',
            'questions_per_page' => 'تعداد سؤال در هر صفحه',
            'negative_marking' => 'نمرهٔ منفی',
            'max_attempts' => 'دفعات مجاز شرکت',
            'retake_policy' => 'شرکت دوباره',
            'result_visibility' => 'نمایش نتیجه به شرکت‌کننده',
            'shuffle_questions' => 'ترتیب سؤال‌ها تصادفی باشد',
            'shuffle_options' => 'ترتیب گزینه‌ها تصادفی باشد',
        ];
    }
}
