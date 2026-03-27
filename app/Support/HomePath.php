<?php

declare(strict_types=1);

namespace App\Support;

final class HomePath
{
    public static function forAuth(?array $auth): string
    {
        $planCode = strtoupper(trim((string) ($auth['plan_code'] ?? 'FREE')));
        return in_array($planCode, ['MEDIUM', 'PRO'], true) ? '/dashboard' : '/calculator';
    }

    public static function dashboardAllowed(?array $auth): bool
    {
        $planCode = strtoupper(trim((string) ($auth['plan_code'] ?? 'FREE')));
        return in_array($planCode, ['MEDIUM', 'PRO'], true);
    }
}
