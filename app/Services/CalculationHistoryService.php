<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CalculationHistoryRepository;
use App\Support\Database;

final class CalculationHistoryService
{
    private CalculationHistoryRepository $histories;

    public function __construct()
    {
        $this->histories = new CalculationHistoryRepository(Database::connection());
    }

    /**
     * @param array<string, mixed> $auth
     * @param array<string, mixed> $input
     * @param array<string, mixed> $output
     */
    public function store(array $auth, array $input, array $output): ?int
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $payload = [
            'user_id' => $userId,
            'item_id' => isset($input['item_id']) ? (int) $input['item_id'] : null,
            'plan_code' => (string) ($auth['plan_code'] ?? 'FREE'),
            'calculation_mode' => (string) ($output['calculation_mode'] ?? 'MANUAL'),
            'input_snapshot' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'output_snapshot' => json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        if ($payload['input_snapshot'] === false || $payload['output_snapshot'] === false) {
            return null;
        }

        return $this->histories->create($payload);
    }

    /**
     * @return array{
     *   total_count: int,
     *   recent_count: int,
     *   recent_profit_count: int,
     *   recent_loss_count: int,
     *   recent_total_profit: float,
     *   recent_avg_margin: float,
     *   latest: array<string, mixed>|null,
     *   recent_rows: array<int, array<string, mixed>>
     * }
     */
    public function dashboardSummary(int $userId, int $limit = 20): array
    {
        $rows = $this->histories->listRecentByUser($userId, $limit);
        $recentRows = [];
        $recentTotalProfit = 0.0;
        $recentAvgMarginAccumulator = 0.0;
        $recentAvgMarginCount = 0;
        $recentProfitCount = 0;
        $recentLossCount = 0;

        foreach ($rows as $row) {
            $input = json_decode((string) ($row['input_snapshot'] ?? '{}'), true);
            $output = json_decode((string) ($row['output_snapshot'] ?? '{}'), true);
            if (! is_array($input)) {
                $input = [];
            }
            if (! is_array($output)) {
                $output = [];
            }

            $scenario = is_array($output['scenario'] ?? null) ? $output['scenario'] : [];
            $scenarioProfit = isset($scenario['total_profit']) ? (float) $scenario['total_profit'] : null;
            $scenarioMargin = isset($scenario['margin_percent']) ? (float) $scenario['margin_percent'] : null;

            if ($scenarioProfit !== null) {
                $recentTotalProfit += $scenarioProfit;
                if ($scenarioProfit >= 0) {
                    $recentProfitCount++;
                } else {
                    $recentLossCount++;
                }
            }

            if ($scenarioMargin !== null) {
                $recentAvgMarginAccumulator += $scenarioMargin;
                $recentAvgMarginCount++;
            }

            $recentRows[] = [
                'id' => (int) ($row['id'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'item_name' => (string) (($input['item_name'] ?? '') !== '' ? $input['item_name'] : ($row['item_name_db'] ?? '-')),
                'plan_code' => (string) ($row['plan_code'] ?? 'FREE'),
                'calculation_mode' => (string) ($row['calculation_mode'] ?? 'MANUAL'),
                'total_output' => isset($output['total_output']) ? (int) $output['total_output'] : 0,
                'production_cost' => isset($output['production_cost']) ? (float) $output['production_cost'] : 0.0,
                'scenario_sell_price' => isset($scenario['sell_price']) ? (float) $scenario['sell_price'] : null,
                'scenario_profit' => $scenarioProfit,
                'scenario_margin' => $scenarioMargin,
                'status' => (string) ($output['status'] ?? '-'),
                'scenario_mode' => (string) ($scenario['mode'] ?? '-'),
            ];
        }

        return [
            'total_count' => $this->histories->countByUser($userId),
            'recent_count' => count($recentRows),
            'recent_profit_count' => $recentProfitCount,
            'recent_loss_count' => $recentLossCount,
            'recent_total_profit' => round($recentTotalProfit, 2),
            'recent_avg_margin' => $recentAvgMarginCount > 0 ? round($recentAvgMarginAccumulator / $recentAvgMarginCount, 2) : 0.0,
            'latest' => $recentRows[0] ?? null,
            'recent_rows' => $recentRows,
        ];
    }
}
