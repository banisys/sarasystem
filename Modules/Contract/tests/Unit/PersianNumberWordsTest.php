<?php

namespace Modules\Contract\Tests\Unit;

use App\Support\PersianNumberWords;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PersianNumberWordsTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function numbers(): array
    {
        return [
            'zero' => [0, 'صفر'],
            'one digit' => [7, 'هفت'],
            'teen' => [12, 'دوازده'],
            'tens' => [45, 'چهل و پنج'],
            'hundreds' => [205, 'دویست و پنج'],
            'thousand' => [1000, 'یک هزار'],
            'million' => [1250000, 'یک میلیون و دویست و پنجاه هزار'],
            'gaps' => [3000017, 'سه میلیون و هفده'],
            'billion' => [12000000000, 'دوازده میلیارد'],
        ];
    }

    #[DataProvider('numbers')]
    public function test_numbers_in_words(int $number, string $words): void
    {
        $this->assertSame($words, PersianNumberWords::convert($number));
    }
}
