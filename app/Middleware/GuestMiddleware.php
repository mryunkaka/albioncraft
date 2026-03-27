<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\HomePath;

final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): bool
    {
        if (! Session::has('auth')) {
            return true;
        }

        $auth = Session::get('auth');
        Response::redirect(HomePath::forAuth(is_array($auth) ? $auth : null));
        return false;
    }
}
