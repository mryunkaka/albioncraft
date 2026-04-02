<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuthSessionRepository;
use App\Repositories\UserRepository;
use App\Support\Database;
use App\Support\Env;
use App\Support\Session;

final class AuthSessionService
{
    private const PERSISTENT_COOKIE = 'albion_persistent_auth';
    private const PERSISTENT_COOKIE_LIFETIME = 157680000;

    private AuthSessionRepository $sessions;
    private UserRepository $users;

    public function __construct()
    {
        $db = Database::connection();
        $this->sessions = new AuthSessionRepository($db);
        $this->users = new UserRepository($db);
    }

    public function issue(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        $this->sessions->upsert($userId, $token);
        return $token;
    }

    /**
     * @param array<string, mixed>|null $auth
     */
    public function isValid(?array $auth): bool
    {
        if (! is_array($auth)) {
            return false;
        }

        $userId = (int) ($auth['user_id'] ?? 0);
        $sessionToken = (string) ($auth['session_token'] ?? '');
        if ($userId <= 0 || $sessionToken === '') {
            return false;
        }

        $storedToken = $this->sessions->findTokenByUserId($userId);
        return $storedToken !== null && hash_equals($storedToken, $sessionToken);
    }

    /**
     * @param array<string, mixed>|null $auth
     */
    public function invalidateCurrent(?array $auth): void
    {
        if (! is_array($auth)) {
            $this->clearPersistentCookie();
            return;
        }

        $userId = (int) ($auth['user_id'] ?? 0);
        $sessionToken = (string) ($auth['session_token'] ?? '');
        if ($userId <= 0 || $sessionToken === '') {
            $this->clearPersistentCookie();
            return;
        }

        $this->sessions->deleteByUserAndToken($userId, $sessionToken);
        $this->clearPersistentCookie();
    }

    public function destroyInvalidSession(): void
    {
        $this->clearPersistentCookie();

        if (! Session::has('auth')) {
            return;
        }

        Session::destroy();
        Session::start();
    }

    /**
     * @param array<string, mixed>|null $auth
     */
    public function persistCurrent(?array $auth): void
    {
        if (! is_array($auth)) {
            $this->clearPersistentCookie();
            return;
        }

        $userId = (int) ($auth['user_id'] ?? 0);
        $sessionToken = (string) ($auth['session_token'] ?? '');
        if ($userId <= 0 || $sessionToken === '') {
            $this->clearPersistentCookie();
            return;
        }

        $payloadJson = json_encode([
            'user_id' => $userId,
            'session_token' => $sessionToken,
        ], JSON_UNESCAPED_SLASHES);

        if (! is_string($payloadJson) || $payloadJson === '') {
            $this->clearPersistentCookie();
            return;
        }

        $encodedPayload = $this->base64UrlEncode($payloadJson);
        $signature = hash_hmac('sha256', $encodedPayload, $this->cookieSecret());

        $this->setPersistentCookie($encodedPayload . '.' . $signature, time() + self::PERSISTENT_COOKIE_LIFETIME);
    }

    public function bootstrap(): void
    {
        $auth = Session::get('auth');
        if (is_array($auth)) {
            if ($this->isValid($auth)) {
                $freshAuth = $this->hydrateAuthPayload(
                    (int) ($auth['user_id'] ?? 0),
                    (string) ($auth['session_token'] ?? '')
                );

                if ($freshAuth !== null) {
                    Session::put('auth', $freshAuth);
                    $this->persistCurrent($freshAuth);
                    return;
                }
            }

            $this->destroyInvalidSession();
        }

        $restored = $this->restoreFromPersistentCookie();
        if ($restored === null) {
            return;
        }

        Session::regenerate();
        Session::put('auth', $restored);
        $this->persistCurrent($restored);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function restoreFromPersistentCookie(): ?array
    {
        $raw = (string) ($_COOKIE[self::PERSISTENT_COOKIE] ?? '');
        if ($raw === '') {
            return null;
        }

        $parts = explode('.', $raw, 2);
        if (count($parts) !== 2) {
            $this->clearPersistentCookie();
            return null;
        }

        [$encodedPayload, $signature] = $parts;
        $expectedSignature = hash_hmac('sha256', $encodedPayload, $this->cookieSecret());
        if ($signature === '' || ! hash_equals($expectedSignature, $signature)) {
            $this->clearPersistentCookie();
            return null;
        }

        $decoded = $this->base64UrlDecode($encodedPayload);
        if (! is_string($decoded) || $decoded === '') {
            $this->clearPersistentCookie();
            return null;
        }

        $payload = json_decode($decoded, true);
        if (! is_array($payload)) {
            $this->clearPersistentCookie();
            return null;
        }

        $userId = (int) ($payload['user_id'] ?? 0);
        $sessionToken = (string) ($payload['session_token'] ?? '');
        if ($userId <= 0 || $sessionToken === '') {
            $this->clearPersistentCookie();
            return null;
        }

        $storedToken = $this->sessions->findTokenByUserId($userId);
        if ($storedToken === null || ! hash_equals($storedToken, $sessionToken)) {
            $this->clearPersistentCookie();
            return null;
        }

        return $this->hydrateAuthPayload($userId, $sessionToken);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function hydrateAuthPayload(int $userId, string $sessionToken): ?array
    {
        $user = $this->users->findWithPlanById($userId);
        if ($user === null || (string) ($user['status'] ?? 'ACTIVE') !== 'ACTIVE') {
            $this->clearPersistentCookie();
            return null;
        }

        return [
            'user_id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
            'plan_id' => (int) $user['plan_id'],
            'plan_code' => (string) ($user['plan_code'] ?? 'FREE'),
            'plan_name' => (string) ($user['plan_name'] ?? 'Free'),
            'plan_expired_at' => $user['plan_expired_at'] ?? null,
            'session_token' => $sessionToken,
        ];
    }

    private function cookieSecret(): string
    {
        $configured = trim(Env::get('AUTH_PERSISTENT_SECRET', ''));
        if ($configured !== '') {
            return $configured;
        }

        $fallback = implode('|', [
            Env::get('APP_ENV', 'production'),
            Env::get('DB_NAME', ''),
            Env::get('DB_USER', ''),
            Env::get('DB_PASS', ''),
        ]);

        return hash('sha256', $fallback !== 'production|||' ? $fallback : __FILE__);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string|false
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }

    private function clearPersistentCookie(): void
    {
        unset($_COOKIE[self::PERSISTENT_COOKIE]);
        $this->setPersistentCookie('', time() - 42000);
    }

    private function setPersistentCookie(string $value, int $expiresAt): void
    {
        setcookie(self::PERSISTENT_COOKIE, $value, [
            'expires' => $expiresAt,
            'path' => '/',
            'secure' => $this->isSecureRequest(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function isSecureRequest(): bool
    {
        if (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $forwardedProto === 'https';
    }
}
