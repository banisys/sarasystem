<?php

namespace Modules\Course\Enums;

use Filament\Support\Contracts\HasLabel;

enum CourseLessonType: string implements HasLabel
{
    case File = 'file';
    case Video = 'video';
    case Exam = 'exam';

    public function getLabel(): string
    {
        return match ($this) {
            self::File => 'فایل',
            self::Video => 'ویدیو',
            self::Exam => 'آزمون',
        };
    }
}
