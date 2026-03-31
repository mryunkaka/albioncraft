<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\AuthSessionService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\HomePath;

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        $auth = Session::get('auth');
        if (! is_array($auth)) {
            return true;
        }

        $authSessions = new AuthSessionService();
        if (! $authSessions->isValid($auth)) {
            $authSessions->destroyInvalidSession();
            return true;
        }

        Response::redirect(HomePath::forAuth($auth));
        return false;
    }
}
