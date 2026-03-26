<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ReferralRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByReferredUserId(int $referredUserId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM referrals WHERE referred_user_id = :id LIMIT 1');
        $stmt->execute(['id' => $referredUserId]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function create(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO referrals (referrer_user_id, referred_user_id, referral_code_used, status)
             VALUES (:referrer_user_id, :referred_user_id, :referral_code_used, :status)'
        );
        $stmt->execute([
            'referrer_user_id' => $payload['referrer_user_id'],
            'referred_user_id' => $payload['referred_user_id'],
            'referral_code_used' => $payload['referral_code_used'],
            'status' => $payload['status'] ?? 'VALID',
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function createReward(array $payload): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO referral_rewards (referral_id, rewarded_user_id, reward_type, reward_days, notes)
             VALUES (:referral_id, :rewarded_user_id, :reward_type, :reward_days, :notes)'
        );
        $stmt->execute([
            'referral_id' => $payload['referral_id'],
            'rewarded_user_id' => $payload['rewarded_user_id'],
            'reward_type' => $payload['reward_type'] ?? 'SUBSCRIPTION_DAYS',
            'reward_days' => $payload['reward_days'] ?? 0,
            'notes' => $payload['notes'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRewardsByUserId(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT rr.*, r.referral_code_used, u.username AS referred_username
             FROM referral_rewards rr
             JOIN referrals r ON r.id = rr.referral_id
             LEFT JOIN users u ON u.id = r.referred_user_id
             WHERE rr.rewarded_user_id = :user_id
             ORDER BY rr.id DESC
             LIMIT :limit_value'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit_value', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}

