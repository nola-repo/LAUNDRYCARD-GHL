<?php

namespace App\Support;

class Normalizer
{
    public static function email(?string $email): ?string
    {
        $e = strtolower(trim((string) $email));

        return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
    }

    /** Normalise to E.164. Returns null if the value cannot be trusted as a phone number. */
    public static function phone(?string $phone, ?string $defaultCountry = null): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }
        $defaultCountry ??= config('integration.ghl.default_country_code', '1');
        $hasPlus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D/', '', $raw);

        if ($hasPlus) {
            return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+'.$digits : null;
        }
        if ($defaultCountry === '1') {
            if (strlen($digits) === 10) {
                return '+1'.$digits;
            }
            if (strlen($digits) === 11 && $digits[0] === '1') {
                return '+'.$digits;
            }

            return null;
        }

        return strlen($digits) >= 8 && strlen($digits) <= 15 ? '+'.$defaultCountry.ltrim($digits, '0') : null;
    }

    public static function hash(?string $value): ?string
    {
        return $value === null ? null : hash_hmac('sha256', $value, (string) config('app.key'));
    }

    public static function storeId(int|string $id): string
    {
        return str_pad(ltrim((string) $id, ' '), (int) config('integration.laundrycard.store_id_pad', 4), '0', STR_PAD_LEFT);
    }

    public static function splitName(?string $name): array
    {
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));
        if ($name === '') {
            return [null, null];
        }
        $parts = explode(' ', $name, 2);

        return [$parts[0], $parts[1] ?? null];
    }
}
