<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\AdminAccess;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

final class AdminMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['email'])) {
            Session::flash('error', 'Silakan login dulu.');
            Response::redirect('/login');
            return false;
        }

        $email = (string) $auth['email'];
        if (AdminAccess::isAdminEmail($email)) {
            return true;
        }

        Session::flash('error', 'Akses ditolak. Hanya admin yang diizinkan.');
        Response::redirect('/dashboard');
        return false;
    }
}

