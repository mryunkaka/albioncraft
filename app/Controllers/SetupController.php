<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Database;
use App\Support\Env;
use App\Support\Request;
use App\Support\Response;

final class SetupController
{
    public function seed(Request $request): void
    {
        $token = trim((string) $request->input('token', ''));
        $expected = trim(Env::get('SETUP_TOKEN', ''));
        $debug = Env::get('APP_DEBUG', '0') === '1';

        if ($expected !== '') {
            if ($token === '' || ! hash_equals($expected, $token)) {
                Response::html('Forbidden', 403);
                return;
            }
        } elseif (! $debug) {
            Response::html('Forbidden', 403);
            return;
        }

        $db = Database::connection();

        $queries = [
            "INSERT INTO plans (code, name, sort_order) VALUES
             ('FREE', 'Free', 1),
             ('LITE', 'Lite', 2),
             ('MEDIUM', 'Medium', 3),
             ('PRO', 'Pro', 4)
             ON DUPLICATE KEY UPDATE
               name = VALUES(name),
               sort_order = VALUES(sort_order)",
            "INSERT INTO plan_features (plan_id, feature_key, is_enabled)
             SELECT id, 'calculator_manual', 1 FROM plans
             ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)",
            "INSERT INTO plan_features (plan_id, feature_key, is_enabled)
             SELECT id, 'price_bulk_input', CASE WHEN code = 'PRO' THEN 1 ELSE 0 END FROM plans
             ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)",
            "INSERT INTO plan_features (plan_id, feature_key, is_enabled)
             SELECT id, 'recipe_auto_fill', CASE WHEN code IN ('MEDIUM', 'PRO') THEN 1 ELSE 0 END FROM plans
             ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)",
        ];

        foreach ($queries as $sql) {
            $db->exec($sql);
        }

        $currentDb = (string) ($db->query('SELECT DATABASE()')->fetchColumn() ?: '');
        $plansCount = (int) ($db->query('SELECT COUNT(*) FROM plans')->fetchColumn() ?: 0);
        $freeId = $db->query("SELECT id FROM plans WHERE TRIM(UPPER(code))='FREE' LIMIT 1")->fetchColumn();

        $out = [
            'SEED_STATUS=OK',
            'DATABASE=' . $currentDb,
            'PLANS_COUNT=' . $plansCount,
            'FREE_ID=' . (($freeId === false || $freeId === null) ? 'NULL' : (string) $freeId),
            'NEXT=Try register again',
        ];

        Response::html('<pre>' . htmlspecialchars(implode("\n", $out), ENT_QUOTES, 'UTF-8') . '</pre>');
    }
}
