<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuthSessionRepository;
use App\Support\Database;
use App\Support\Session;

final class AuthSessionService
{
    private AuthSessionRepository $sessions;

    public function __construct()
    {
        $this->sessions = new AuthSessionRepository(Database::connection());
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
            return;
        }

        $userId = (int) ($auth['user_id'] ?? 0);
        $sessionToken = (string) ($auth['session_token'] ?? '');
        if ($userId <= 0 || $sessionToken === '') {
            return;
        }

        $this->sessions->deleteByUserAndToken($userId, $sessionToken);
    }

    public function destroyInvalidSession(): void
    {
        Session::destroy();
        Session::start();
    }
}
