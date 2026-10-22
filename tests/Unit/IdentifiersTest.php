<?php

namespace Tests\Unit;

use App\Support\Identifiers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IdentifiersTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function mobiles(): array
    {
        return [
            'local' => ['09121234567', '09121234567'],
            'without zero' => ['9121234567', '09121234567'],
            'with +98' => ['+989121234567', '09121234567'],
            'with 0098' => ['00989121234567', '09121234567'],
            'with 98' => ['989121234567', '09121234567'],
            'persian digits' => ['۰۹۱۲۱۲۳۴۵۶۷', '09121234567'],
            'spaces and dashes' => ['0912 123-4567', '09121234567'],
            'too short' => ['0912123456', null],
            'landline' => ['02112345678', null],
            'email' => ['a@b.com', null],
        ];
    }

    #[DataProvider('mobiles')]
    public function test_mobiles_are_normalized(string $input, ?string $expected): void
    {
        $this->assertSame($expected, Identifiers::normalizeMobile($input));
    }

    public function test_national_code_check_digit(): void
    {
        $this->assertTrue(Identifiers::isValidNationalCode('0084575948'));
        $this->assertTrue(Identifiers::isValidNationalCode('۰۰۸۴۵۷۵۹۴۸'));
        $this->assertFalse(Identifiers::isValidNationalCode('0084575947'));
        $this->assertFalse(Identifiers::isValidNationalCode('1111111111'));
        $this->assertFalse(Identifiers::isValidNationalCode('12345'));
    }
}
