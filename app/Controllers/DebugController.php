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

        $lines = [
            'APP_DEBUG=' . Env::get('APP_DEBUG', '0'),
            'ENV_DB_NAME=' . Env::get('DB_NAME', ''),
        ];

        try {
            $db = Database::connection();
            $plans = new PlanRepository($db);

            $currentDb = (string) ($db->query('SELECT DATABASE()')->fetchColumn() ?: '');
            $currentUser = (string) ($db->query('SELECT CURRENT_USER()')->fetchColumn() ?: '');
            $serverHost = (string) ($db->query('SELECT @@hostname')->fetchColumn() ?: '');
            $serverPort = (string) ($db->query('SELECT @@port')->fetchColumn() ?: '');
            $plansCount = (int) ($db->query('SELECT COUNT(*) FROM plans')->fetchColumn() ?: 0);
            $plansCodes = (string) ($db->query("SELECT COALESCE(GROUP_CONCAT(code ORDER BY id SEPARATOR ','), '') FROM plans")->fetchColumn() ?: '');
            $freeIdRaw = $plans->findIdByCode('FREE');
            $firstPlanId = $plans->findFirstPlanId();

            $lines[] = 'PDO_DATABASE()=' . $currentDb;
            $lines[] = 'PDO_CURRENT_USER()=' . $currentUser;
            $lines[] = 'MYSQL_HOSTNAME=' . $serverHost;
            $lines[] = 'MYSQL_PORT=' . $serverPort;
            $lines[] = 'PLANS_COUNT=' . $plansCount;
            $lines[] = 'PLANS_CODES=' . $plansCodes;
            $lines[] = 'FREE_ID=' . ($freeIdRaw === null ? 'NULL' : (string) $freeIdRaw);
            $lines[] = 'FIRST_PLAN_ID=' . ($firstPlanId === null ? 'NULL' : (string) $firstPlanId);
        } catch (\Throwable $e) {
            $lines[] = 'DEBUG_ERROR=' . $e->getMessage();
        }

        Response::html('<pre>' . htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8') . '</pre>');
    }
}
