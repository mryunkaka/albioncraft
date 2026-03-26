<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\PlanRepository;
use App\Support\Database;
use App\Support\Env;
use App\Support\Request;
use App\Support\Response;

final class DebugController
{
    public function db(Request $request): void
    {
        if (Env::get('APP_DEBUG', '0') !== '1') {
            Response::html('Forbidden', 403);
            return;
        }

        $db = Database::connection();
        $plans = new PlanRepository($db);

        $currentDb = (string) ($db->query('SELECT DATABASE()')->fetchColumn() ?: '');
        $plansCount = (int) ($db->query('SELECT COUNT(*) FROM plans')->fetchColumn() ?: 0);
        $freeIdRaw = $plans->findIdByCode('FREE');
        $firstPlanId = $plans->findFirstPlanId();

        $lines = [
            'APP_DEBUG=' . Env::get('APP_DEBUG', '0'),
            'ENV_DB_NAME=' . Env::get('DB_NAME', ''),
            'PDO_DATABASE()=' . $currentDb,
            'PLANS_COUNT=' . $plansCount,
            'FREE_ID=' . ($freeIdRaw === null ? 'NULL' : (string) $freeIdRaw),
            'FIRST_PLAN_ID=' . ($firstPlanId === null ? 'NULL' : (string) $firstPlanId),
        ];

        Response::html('<pre>' . htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8') . '</pre>');
    }
}

