<?php

namespace App\Support;

/**
 * Removes secrets and unnecessary personal data from anything that is persisted
 * to logs, diagnostics or error messages.
 */
class Sanitizer
{
    private const SECRET_KEYS = [
        'token', 'access_token', 'refresh_token', 'authorization', 'password', 'secret',
        'client_secret', 'code', 'state', 'api_key', 'bearer',
    ];

    private const PII_KEYS = ['email', 'phone', 'name', 'firstname', 'lastname', 'address', 'customer_email', 'customer_phone', 'customer_name', 'number'];

    public static function context(array $data, int $depth = 0): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $lk = strtolower((string) $key);
            if (self::matches($lk, self::SECRET_KEYS)) {
                $out[$key] = '[REDACTED]';
            } elseif (self::matches($lk, self::PII_KEYS) && ! is_array($value)) {
                $out[$key] = $value === null ? null : '[PII]';
            } elseif (is_array($value)) {
                $out[$key] = $depth > 4 ? '[TRUNCATED]' : self::context($value, $depth + 1);
            } elseif (is_string($value)) {
                $out[$key] = self::string($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    public static function string(?string $value, int $limit = 500): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = preg_replace('/Bearer\s+[A-Za-z0-9\-\._~\+\/]+=*/i', 'Bearer [REDACTED]', $value);
        $v = preg_replace('/eyJ[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]*/', '[JWT]', $v);
        $v = preg_replace('/("?(?:access_token|refresh_token|client_secret|password|secret|code|token|Token|Password)"?\s*[:=]\s*)"?[^"&\s,}]+"?/i', '$1[REDACTED]', $v);
        $v = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[EMAIL]', $v);
        $v = preg_replace('/(?<![\w\-])\+?\d[\d\-\s().]{8,}\d(?![\w\-])/', '[PHONE]', $v);

        return mb_substr($v, 0, $limit);
    }

    private static function matches(string $key, array $needles): bool
    {
        foreach ($needles as $n) {
            if ($key === $n || ($n !== 'code' && $n !== 'state' && $n !== 'name' && $n !== 'number' && str_contains($key, $n))) {
                return true;
            }
        }

        return false;
    }

    public static function maskEmail(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return $email;
        }
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).'***@'.$domain;
    }

    public static function maskPhone(?string $phone): ?string
    {
        if (! $phone) {
            return $phone;
        }
        $d = preg_replace('/\D/', '', $phone);

        return strlen($d) > 4 ? str_repeat('*', strlen($d) - 4).substr($d, -4) : '****';
    }
}
