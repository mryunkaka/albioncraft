<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthSessionService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        $auth = Session::get('auth');
        $authSessions = new AuthSessionService();
        if (is_array($auth) && $authSessions->isValid($auth)) {
            return true;
        }

        $authSessions->destroyInvalidSession();

        if ($request->isAjax()) {
            Response::json([
                'success' => false,
                'message' => 'Silakan login dulu. Session login tidak ditemukan atau sudah berakhir.',
                'error_code' => 'AUTH_REQUIRED',
                'redirect_to' => '/login',
            ], 401);
            return false;
        }

        Session::flash('error', 'Silakan login dulu.');
        Response::redirect('/login');
        return false;
    }
}
