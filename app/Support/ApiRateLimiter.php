<?php

declare(strict_types=1);

namespace App\Support;

final class ApiRateLimiter
{
    private const SESSION_KEY = '_api_rate_limit';

    /**
     * @return array{allowed: bool, retry_after: int}
     */
    public static function check(string $scope, Request $request): array
    {
        $maxAttempts = self::maxAttempts();
        $windowSeconds = self::windowSeconds();

        $key = self::key($scope, $request);
        $bucket = self::bucket($key, $windowSeconds);
        $attempts = (int) ($bucket['attempts'] ?? 0);
        $windowStartedAt = (int) ($bucket['window_started_at'] ?? time());

        if ($attempts < $maxAttempts) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $retryAfter = max(0, ($windowStartedAt + $windowSeconds) - time());

        return [
            'allowed' => $retryAfter <= 0,
            'retry_after' => $retryAfter,
        ];
    }

    /**
     * @return array{allowed: bool, retry_after: int}
     */
    public static function hit(string $scope, Request $request): array
    {
        $maxAttempts = self::maxAttempts();
        $windowSeconds = self::windowSeconds();

        $key = self::key($scope, $request);
        $all = Session::get(self::SESSION_KEY, []);
        if (! is_array($all)) {
            $all = [];
        }

        $bucket = self::bucket($key, $windowSeconds);
        $attempts = (int) ($bucket['attempts'] ?? 0) + 1;
        $windowStartedAt = (int) ($bucket['window_started_at'] ?? time());

        $all[$key] = [
            'attempts' => $attempts,
            'window_started_at' => $windowStartedAt,
        ];
        Session::put(self::SESSION_KEY, $all);

        if ($attempts < $maxAttempts) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        $retryAfter = max(0, ($windowStartedAt + $windowSeconds) - time());

        return [
            'allowed' => $retryAfter <= 0,
            'retry_after' => $retryAfter,
        ];
    }

    public static function clear(string $scope, Request $request): void
    {
        $key = self::key($scope, $request);
        $all = Session::get(self::SESSION_KEY, []);
        if (! is_array($all) || ! array_key_exists($key, $all)) {
            return;
        }

        unset($all[$key]);
        Session::put(self::SESSION_KEY, $all);
    }

    private static function maxAttempts(): int
    {
        $value = (int) Env::get('API_RATE_LIMIT_MAX_ATTEMPTS', '30');
        return $value > 0 ? $value : 30;
    }

    private static function windowSeconds(): int
    {
        $value = (int) Env::get('API_RATE_LIMIT_WINDOW_SECONDS', '60');
        return $value > 0 ? $value : 60;
    }

    /**
     * @return array{attempts: int, window_started_at: int}
     */
    private static function bucket(string $key, int $windowSeconds): array
    {
        $all = Session::get(self::SESSION_KEY, []);
        if (! is_array($all)) {
            return ['attempts' => 0, 'window_started_at' => time()];
        }

        $bucket = $all[$key] ?? null;
        if (! is_array($bucket)) {
            return ['attempts' => 0, 'window_started_at' => time()];
        }

        $windowStartedAt = (int) ($bucket['window_started_at'] ?? 0);
        if ($windowStartedAt <= 0 || (time() - $windowStartedAt) >= $windowSeconds) {
            return ['attempts' => 0, 'window_started_at' => time()];
        }

        return [
            'attempts' => (int) ($bucket['attempts'] ?? 0),
            'window_started_at' => $windowStartedAt,
        ];
    }

    private static function key(string $scope, Request $request): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
        $fingerprint = hash('sha256', $ip . '|' . $ua);

        return $scope . ':' . substr($fingerprint, 0, 24);
    }
}
