<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminUserManagementService;
use App\Support\AdminAccess;
use App\Support\Csrf;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

final class AdminUserManagementController
{
    public function index(Request $request): void
    {
        $auth = Session::get('auth');
        $service = new AdminUserManagementService();
        $overview = $service->overview($request->query());

        Response::html(View::render('admin/users', [
            'auth' => $auth,
            'overview' => $overview,
            'is_admin' => AdminAccess::isAdminEmail(is_array($auth) ? (string) ($auth['email'] ?? '') : ''),
            'csrf_token' => Csrf::token(),
            'flash_success' => Session::pullFlash('success'),
            'flash_error' => Session::pullFlash('error'),
        ]));
    }

    public function updateProfile(Request $request): void
    {
        $userId = (int) $request->input('user_id', 0);
        $auth = Session::get('auth');
        $actor = is_array($auth) ? (string) ($auth['email'] ?? 'ADMIN') : 'ADMIN';

        $service = new AdminUserManagementService();
        $result = $service->updateProfile(
            $userId,
            (string) $request->input('username', ''),
            (string) $request->input('email', ''),
            (string) $request->input('status', 'ACTIVE'),
            $actor
        );

        $this->flashAndRedirect($result['ok'], $result['message'], $userId);
    }

    public function updatePassword(Request $request): void
    {
        $userId = (int) $request->input('user_id', 0);
        $auth = Session::get('auth');
        $actor = is_array($auth) ? (string) ($auth['email'] ?? 'ADMIN') : 'ADMIN';

        $service = new AdminUserManagementService();
        $result = $service->resetPassword(
            $userId,
            (string) $request->input('new_password', ''),
            (string) $request->input('new_password_confirmation', ''),
            $actor
        );

        $this->flashAndRedirect($result['ok'], $result['message'], $userId);
    }

    public function updatePlan(Request $request): void
    {
        $userId = (int) $request->input('user_id', 0);
        $auth = Session::get('auth');
        $actor = is_array($auth) ? (string) ($auth['email'] ?? 'ADMIN') : 'ADMIN';

        $service = new AdminUserManagementService();
        $result = $service->updatePlan(
            $userId,
            (string) $request->input('plan_code', 'FREE'),
            (string) $request->input('expired_at', ''),
            $actor
        );

        $this->flashAndRedirect($result['ok'], $result['message'], $userId);
    }

    private function flashAndRedirect(bool $ok, string $message, int $userId): void
    {
        Session::flash($ok ? 'success' : 'error', $message);
        $target = '/admin/users';
        if ($userId > 0) {
            $target .= '?user_id=' . $userId;
        }

        Response::redirect($target);
    }
}
