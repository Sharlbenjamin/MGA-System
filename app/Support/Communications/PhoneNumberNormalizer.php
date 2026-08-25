<?php

namespace App\Support\Communications;

class PhoneNumberNormalizer
{
    public function __construct(
        protected ?string $defaultCountryCode = null,
    ) {
        $this->defaultCountryCode ??= (string) config('communications.whatsapp.default_country_code', '353');
    }

    /**
     * Normalize to E.164 digits without leading plus (for wa.me URLs).
     */
    public function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        $defaultCode = ltrim($this->defaultCountryCode, '+');

        if (str_starts_with($phone, '+')) {
            return ltrim($digits, '0') ?: null;
        }

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2) ?: null;
        }

        if (str_starts_with($digits, '0')) {
            return $defaultCode.ltrim($digits, '0');
        }

        if (strlen($digits) <= 10 && $defaultCode !== '') {
            return $defaultCode.$digits;
        }

        return $digits;
    }

    public function buildWhatsAppUrl(string $normalizedPhone, string $message): string
    {
        $phone = ltrim($normalizedPhone, '+');

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($message);
    }
}
