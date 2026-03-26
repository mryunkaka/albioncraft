<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => trim($username)]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByReferralCode(string $referralCode): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE referral_code = :code LIMIT 1');
        $stmt->execute(['code' => strtoupper(trim($referralCode))]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findWithPlanById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, p.code AS plan_code, p.name AS plan_name
             FROM users u
             JOIN plans p ON p.id = u.plan_id
             WHERE u.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, email, password_hash, referral_code, referred_by_code, plan_id, plan_expired_at, status)
             VALUES (:username, :email, :password_hash, :referral_code, :referred_by_code, :plan_id, :plan_expired_at, :status)'
        );

        $stmt->execute([
            'username' => $payload['username'],
            'email' => $payload['email'],
            'password_hash' => $payload['password_hash'],
            'referral_code' => $payload['referral_code'],
            'referred_by_code' => $payload['referred_by_code'],
            'plan_id' => $payload['plan_id'],
            'plan_expired_at' => $payload['plan_expired_at'],
            'status' => $payload['status'] ?? 'ACTIVE',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function updatePlan(int $userId, int $planId, ?string $expiredAt): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users
             SET plan_id = :plan_id, plan_expired_at = :plan_expired_at, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $userId,
            'plan_id' => $planId,
            'plan_expired_at' => $expiredAt,
        ]);
    }
}
