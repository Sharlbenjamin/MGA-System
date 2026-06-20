<?php

namespace App\Support;

class ClientEmailRecipients
{
    /**
     * @return array<int, string>
     */
    public static function parseCommaSeparated(?string $input): array
    {
        if ($input === null || trim($input) === '') {
            return [];
        }

        return self::normalizeList(explode(',', $input));
    }

    /**
     * @param  array<int, string|null>  $emails
     * @return array<int, string>
     */
    public static function normalizeList(array $emails): array
    {
        return collect($emails)
            ->map(fn ($email) => is_string($email) ? trim($email) : '')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $emails
     * @return array{valid: array<int, string>, invalid: array<int, string>}
     */
    public static function validateList(array $emails, ?string $excludeTo = null): array
    {
        $excludeTo = $excludeTo !== null ? strtolower(trim($excludeTo)) : null;

        $valid = [];
        $invalid = [];

        foreach (self::normalizeList($emails) as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $invalid[] = $email;

                continue;
            }

            if ($excludeTo !== null && strtolower($email) === $excludeTo) {
                continue;
            }

            $valid[] = $email;
        }

        return [
            'valid' => array_values(array_unique($valid)),
            'invalid' => array_values(array_unique($invalid)),
        ];
    }
}
