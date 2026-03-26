<?php

declare(strict_types=1);

namespace App\Services;

final class DashboardService
{
    private SubscriptionService $subscriptions;
    private CalculationHistoryService $histories;

    public function __construct()
    {
        $this->subscriptions = new SubscriptionService();
        $this->histories = new CalculationHistoryService();
    }

    /**
     * @return array{
     *   user: array<string, mixed>|null,
     *   calculation_summary: array<string, mixed>
     * }
     */
    public function overview(int $userId): array
    {
        $user = $this->subscriptions->syncUserPlan($userId);
        $summary = $this->histories->dashboardSummary($userId, 20);

        return [
            'user' => $user,
            'calculation_summary' => $summary,
        ];
    }
}
