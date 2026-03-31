<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuthSessionRepository
{
    public function __construct(private PDO $db)
    {
        $this->ensureTable();
    }

    public function upsert(int $userId, string $sessionToken): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO user_auth_sessions (user_id, session_token)
             VALUES (:user_id, :session_token)
             ON DUPLICATE KEY UPDATE
                session_token = VALUES(session_token),
                updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([
            'user_id' => $userId,
            'session_token' => $sessionToken,
        ]);
    }

    public function findTokenByUserId(int $userId): ?string
    {
        $stmt = $this->db->prepare('SELECT session_token FROM user_auth_sessions WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $value = $stmt->fetchColumn();
        return is_string($value) && $value !== '' ? $value : null;
    }

    public function deleteByUserAndToken(int $userId, string $sessionToken): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM user_auth_sessions
             WHERE user_id = :user_id
               AND session_token = :session_token'
        );
        $stmt->execute([
            'user_id' => $userId,
            'session_token' => $sessionToken,
        ]);
    }

    private function ensureTable(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS user_auth_sessions (
                user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                session_token VARCHAR(128) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_user_auth_sessions_user FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
