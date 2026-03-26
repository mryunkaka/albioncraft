<?php

declare(strict_types=1);

namespace App\Support;

final class AdminAccess
{
    public static function isAdminEmail(string $email): bool
    {
        $list = (string) getenv('ADMIN_EMAILS');
        if (trim($list) === '') {
            return false;
        }

        $allowed = array_filter(array_map(
            static fn (string $v): string => strtolower(trim($v)),
            explode(',', $list)
        ));

        return in_array(strtolower(trim($email)), $allowed, true);
    }
}

