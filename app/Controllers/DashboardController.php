<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthSessionService;
use App\Services\DashboardService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;
use App\Support\Csrf;
use App\Support\AdminAccess;
use App\Support\HomePath;

final class DashboardController
{
    public function index(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth)) {
            Session::flash('error', 'Silakan login dulu.');
            Response::redirect('/login');
            return;
        }

        $authSessions = new AuthSessionService();
        if (! $authSessions->isValid($auth)) {
            $authSessions->destroyInvalidSession();
            Session::flash('error', 'Silakan login dulu.');
            Response::redirect('/login');
            return;
        }
        if (! HomePath::dashboardAllowed(is_array($auth) ? $auth : null)) {
            Session::flash('error', 'Dashboard history hanya tersedia untuk plan Medium dan Pro.');
            Response::redirect('/calculator');
            return;
        }

        $email = is_array($auth) ? (string) ($auth['email'] ?? '') : '';
        $userId = is_array($auth) ? (int) ($auth['user_id'] ?? 0) : 0;
        $dashboard = new DashboardService();
        $overview = $dashboard->overview($userId);

        Response::html(View::render('dashboard/index', [
            'user' => $overview['user'] ?? $auth,
            'calculation_summary' => $overview['calculation_summary'] ?? [],
            'is_admin' => AdminAccess::isAdminEmail($email),
            'csrf_token' => Csrf::token(),
            'flash_success' => Session::pullFlash('success'),
            'flash_error' => Session::pullFlash('error'),
        ]));
    }
}
