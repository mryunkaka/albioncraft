<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ReferralService;
use App\Support\Csrf;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

final class ReferralController
{
    public function index(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Session::flash('error', 'Silakan login dulu.');
            Response::redirect('/login');
            return;
        }

        $service = new ReferralService();
        $overview = $service->overview((int) $auth['user_id']);

        Response::html(View::render('referral/index', [
            'user' => Session::get('auth'),
            'overview' => $overview,
            'csrf_token' => Csrf::token(),
            'flash_success' => Session::pullFlash('success'),
            'flash_error' => Session::pullFlash('error'),
        ]));
    }
}

