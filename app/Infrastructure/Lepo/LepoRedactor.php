<?php

declare(strict_types=1);

namespace App\Infrastructure\Lepo;

/**
 * Removes card secrets from Lepo payloads before they are returned, queued,
 * stored or logged: CVV/PIN fields are dropped and card numbers are masked to
 * their last four digits, at any depth and whatever the key's letter case or
 * the value's type.
 */
final class LepoRedactor
{
    private const SECRET_KEYS = ['cvv', 'cvc', 'cvv2', 'pin'];

    private const CARD_NUMBER_KEYS = ['cardno', 'cardnumber', 'pan'];

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function cardSecrets(array $data): array
    {
        foreach ($data as $key => $value) {
            $name = is_string($key) ? strtolower($key) : null;

            if ($name !== null && in_array($name, self::SECRET_KEYS, true)) {
                unset($data[$key]);

                continue;
            }

            if ($name !== null && in_array($name, self::CARD_NUMBER_KEYS, true) && (is_string($value) || is_int($value))) {
                $data[$key] = self::mask((string) $value);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::cardSecrets($value);
            }
        }

        return $data;
    }

    private static function mask(string $number): string
    {
        $length = strlen($number);

        return $length > 4 ? str_repeat('*', $length - 4) . substr($number, -4) : $number;
    }
}
