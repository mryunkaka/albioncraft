<?php

declare(strict_types=1);

namespace App\Support;

final class Session
{
    private const COOKIE_LIFETIME = 157680000;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.gc_maxlifetime', (string) self::COOKIE_LIFETIME);
            ini_set('session.cookie_lifetime', (string) self::COOKIE_LIFETIME);
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_httponly', '1');
            session_set_cookie_params(self::sessionCookieOptions());
            session_start();
        }
    }

    /**
     * @param mixed $value
     */
    public static function put(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', self::cookieOptions(-42000));
        }
        session_destroy();
    }

    public static function flash(string $key, string $message): void
    {
        self::put('_flash_' . $key, $message);
    }

    public static function pullFlash(string $key): ?string
    {
        $sessionKey = '_flash_' . $key;
        $value = self::get($sessionKey);
        self::forget($sessionKey);
        return is_string($value) ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function sessionCookieOptions(): array
    {
        return [
            'lifetime' => self::COOKIE_LIFETIME,
            'path' => '/',
            'secure' => self::isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cookieOptions(int $lifetime): array
    {
        $expires = $lifetime > 0 ? time() + $lifetime : time() + $lifetime;

        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => self::isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private static function isSecureRequest(): bool
    {
        if (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $forwardedProto === 'https';
    }
}
