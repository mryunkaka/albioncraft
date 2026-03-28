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
            if ($request->isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Silakan login dulu. Session user untuk sinkronisasi plan tidak ditemukan.',
                    'error_code' => 'AUTH_REQUIRED',
                    'redirect_to' => '/login',
                ], 401);
                return false;
            }

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

            if ($request->isAjax()) {
                Response::json([
                    'success' => false,
                    'message' => 'Session tidak valid atau plan user gagal disinkronkan. Silakan login ulang.',
                    'error_code' => 'SESSION_INVALID',
                    'redirect_to' => '/login',
                ], 401);
                return false;
            }

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
