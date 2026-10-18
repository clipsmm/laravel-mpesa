<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit\Support;

use LaravelMpesa\Support\PhoneNumber;
use LaravelMpesa\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PhoneNumberTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function acceptedNumbers(): array
    {
        return [
            'e164 without plus' => ['254712345678', '254712345678'],
            'e164 with plus' => ['+254712345678', '254712345678'],
            'national with trunk prefix' => ['0712345678', '254712345678'],
            'national without trunk prefix' => ['712345678', '254712345678'],
            'spaced national' => ['0712 345 678', '254712345678'],
            'spaced international' => ['254 712 345 678', '254712345678'],
        ];
    }

    #[Test]
    #[DataProvider('acceptedNumbers')]
    public function test_formatsAcceptedNumbersToDarajaFormat(string $input, string $expected): void
    {
        $this->assertSame($expected, PhoneNumber::format($input));
        $this->assertTrue(PhoneNumber::isValid($input));
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function rejectedNumbers(): array
    {
        return [
            'empty' => [''],
            'blank' => ['   '],
            'null' => [null],
            'too short' => ['25471234567'],
            'letters' => ['not-a-phone'],
            'non kenyan' => ['+14155552671'],
            'kenyan fixed line' => ['0201234567'],
        ];
    }

    #[Test]
    #[DataProvider('rejectedNumbers')]
    public function test_rejectsInvalidNumbers(?string $input): void
    {
        $this->assertNull(PhoneNumber::format($input));
        $this->assertFalse(PhoneNumber::isValid($input));
    }
}
