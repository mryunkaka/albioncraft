<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

final class DateFormatter
{
    private const WIB_TIMEZONE = 'Asia/Jakarta';
    private const DAYS = [
        'Minggu',
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu',
    ];
    private const MONTHS = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public static function wib(?string $value, bool $withTime = true): string
    {
        $date = self::parse($value);
        if ($date === null) {
            return '-';
        }

        $dayName = self::DAYS[(int) $date->format('w')] ?? '';
        $monthName = self::MONTHS[(int) $date->format('n')] ?? '';
        $base = sprintf(
            '%s, %d %s %d',
            $dayName,
            (int) $date->format('j'),
            $monthName,
            (int) $date->format('Y')
        );

        if (! $withTime) {
            return $base;
        }

        return $base . ' ' . $date->format('H.i') . ' WIB';
    }

    public static function datetimeLocalWib(?string $value): string
    {
        $date = self::parse($value);
        if ($date === null) {
            return '';
        }

        return $date->format('Y-m-d\TH:i');
    }

    private static function parse(?string $value): ?DateTimeImmutable
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $sourceTimezone = new DateTimeZone(date_default_timezone_get());
        try {
            $date = new DateTimeImmutable($raw, $sourceTimezone);
        } catch (\Throwable) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone(self::WIB_TIMEZONE));
    }
}
