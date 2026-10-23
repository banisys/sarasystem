<?php

namespace Modules\Exam\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a participant sees of their attempt once it is submitted.
 */
enum ResultVisibility: string implements HasLabel
{
    case Hidden = 'hidden';

    case PassFail = 'pass_fail';

    case Score = 'score';

    case ScoreWithAnswers = 'score_with_answers';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hidden => 'نمایش داده نشود',
            self::PassFail => 'فقط قبول / مردود',
            self::Score => 'نمره',
            self::ScoreWithAnswers => 'نمره به‌همراه پاسخ‌ها',
        };
    }

    public function showsPassed(): bool
    {
        return $this !== self::Hidden;
    }

    public function showsScore(): bool
    {
        return in_array($this, [self::Score, self::ScoreWithAnswers], true);
    }
}
