<?php

namespace Tests\Unit;

use App\Support\Communications\PhoneNumberNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhoneNumberNormalizerTest extends TestCase
{
    #[DataProvider('normalizationProvider')]
    public function test_normalizes_phone_numbers(string $input, ?string $expected): void
    {
        $normalizer = new PhoneNumberNormalizer('353');

        $this->assertSame($expected, $normalizer->normalize($input));
    }

    public static function normalizationProvider(): array
    {
        return [
            ['+353871234567', '353871234567'],
            ['353871234567', '353871234567'],
            ['0871234567', '353871234567'],
            ['00353871234567', '353871234567'],
            ['', null],
            ['abc', null],
        ];
    }

    public function test_builds_whatsapp_url_with_encoded_message(): void
    {
        $normalizer = new PhoneNumberNormalizer('353');
        $url = $normalizer->buildWhatsAppUrl('353871234567', 'Hello Dr. Smith');

        $this->assertStringStartsWith('https://wa.me/353871234567?text=', $url);
        $this->assertStringContainsString('Hello+Dr.+Smith', $url);
    }
}
