<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\AuthRateLimiter;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

final class AuthRateLimitMiddleware implements MiddlewareInterface
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

        $status = AuthRateLimiter::check($scope, $request);
        if ($status['allowed']) {
            return true;
        }

        $retryAfter = max(1, (int) $status['retry_after']);
        Session::flash('error', "Terlalu banyak percobaan. Coba lagi dalam {$retryAfter} detik.");
        Response::redirect('/' . $scope);
        return false;
    }

    private function scopeFromPath(string $path): ?string
    {
        return match ($path) {
            '/login' => 'login',
            '/register' => 'register',
            default => null,
        };
    }
}
