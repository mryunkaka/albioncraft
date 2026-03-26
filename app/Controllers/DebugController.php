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
        $currentUser = (string) ($db->query('SELECT CURRENT_USER()')->fetchColumn() ?: '');
        $serverHost = (string) ($db->query('SELECT @@hostname')->fetchColumn() ?: '');
        $serverPort = (string) ($db->query('SELECT @@port')->fetchColumn() ?: '');
        $plansCount = (int) ($db->query('SELECT COUNT(*) FROM plans')->fetchColumn() ?: 0);
        $tableTypeStmt = $db->prepare(
            'SELECT TABLE_TYPE
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = :schema_name
               AND TABLE_NAME = :table_name
             LIMIT 1'
        );
        $tableTypeStmt->execute([
            'schema_name' => $currentDb,
            'table_name' => 'plans',
        ]);
        $plansTableType = (string) ($tableTypeStmt->fetchColumn() ?: 'NOT_FOUND');
        $plansCodes = (string) ($db->query("SELECT COALESCE(GROUP_CONCAT(code ORDER BY id SEPARATOR ','), '') FROM plans")->fetchColumn() ?: '');
        $freeIdRaw = $plans->findIdByCode('FREE');
        $firstPlanId = $plans->findFirstPlanId();

        $lines = [
            'APP_DEBUG=' . Env::get('APP_DEBUG', '0'),
            'ENV_DB_NAME=' . Env::get('DB_NAME', ''),
            'PDO_DATABASE()=' . $currentDb,
            'PDO_CURRENT_USER()=' . $currentUser,
            'MYSQL_HOSTNAME=' . $serverHost,
            'MYSQL_PORT=' . $serverPort,
            'PLANS_TABLE_TYPE=' . $plansTableType,
            'PLANS_COUNT=' . $plansCount,
            'PLANS_CODES=' . $plansCodes,
            'FREE_ID=' . ($freeIdRaw === null ? 'NULL' : (string) $freeIdRaw),
            'FIRST_PLAN_ID=' . ($firstPlanId === null ? 'NULL' : (string) $firstPlanId),
        ];

        Response::html('<pre>' . htmlspecialchars(implode("\n", $lines), ENT_QUOTES, 'UTF-8') . '</pre>');
    }
}
