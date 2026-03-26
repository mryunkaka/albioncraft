<?php

declare(strict_types=1);

namespace App\Support;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::SESSION_KEY);
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(32));
        Session::put(self::SESSION_KEY, $token);
        return $token;
    }

    public static function validate(?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $stored = Session::get(self::SESSION_KEY);
        if (! is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }
}

