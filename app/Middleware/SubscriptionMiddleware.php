<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\SubscriptionService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

final class SubscriptionMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Session::flash('error', 'Silakan login dulu.');
            Response::redirect('/login');
            return false;
        }

        $userId = (int) $auth['user_id'];
        $service = new SubscriptionService();
        $user = $service->syncUserPlan($userId);

        if ($user === null) {
            Session::destroy();
            Session::start();
            Session::flash('error', 'Session tidak valid, silakan login ulang.');
            Response::redirect('/login');
            return false;
        }

        Session::put('auth', [
            'user_id' => (int) $user['id'],
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
            'plan_id' => (int) $user['plan_id'],
            'plan_code' => (string) ($user['plan_code'] ?? 'FREE'),
            'plan_name' => (string) ($user['plan_name'] ?? 'Free'),
            'plan_expired_at' => $user['plan_expired_at'] ?? null,
        ]);

        return true;
    }
}

