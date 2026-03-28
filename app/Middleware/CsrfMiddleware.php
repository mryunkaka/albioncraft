<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Csrf;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        $token = $request->input('_token');
        if (Csrf::validate(is_scalar($token) ? (string) $token : null)) {
            return true;
        }

        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '/login');
        $path = parse_url($referer, PHP_URL_PATH);
        $redirectTo = is_string($path) && $path !== '' ? $path : '/login';

        if ($request->isAjax()) {
            Response::json([
                'success' => false,
                'message' => 'CSRF token tidak valid. Reload halaman calculator lalu coba lagi.',
                'error_code' => 'CSRF_INVALID',
                'redirect_to' => $redirectTo,
            ], 419);
            return false;
        }

        Session::flash('error', 'CSRF token tidak valid. Silakan ulangi.');
        Response::redirect($redirectTo);
        return false;
    }
}
