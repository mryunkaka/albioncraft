<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\ApiRateLimiter;
use App\Support\Request;
use App\Support\Response;

final class ApiRateLimitMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if ($request->method() !== 'POST') {
            return true;
        }

        $scope = $this->scopeFromPath($request->path());
        if ($scope === null) {
            return true;
        }

        $status = ApiRateLimiter::hit($scope, $request);
        if ($status['allowed']) {
            return true;
        }

        $retryAfter = max(1, (int) $status['retry_after']);
        header('Retry-After: ' . $retryAfter);
        Response::json([
            'success' => false,
            'message' => "Terlalu banyak request. Coba lagi dalam {$retryAfter} detik.",
        ], 429);
        return false;
    }

    private function scopeFromPath(string $path): ?string
    {
        return match ($path) {
            '/api/calculate' => 'calculate',
            default => null,
        };
    }
}
