<?php

declare(strict_types=1);

namespace App\Support;

final class Session
{
    private const COOKIE_LIFETIME = 315360000;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.gc_maxlifetime', (string) self::COOKIE_LIFETIME);
            session_set_cookie_params([
                'lifetime' => self::COOKIE_LIFETIME,
                'path' => '/',
                'secure' => (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
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
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
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
}
