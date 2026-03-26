<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SubscriptionService;
use App\Support\Csrf;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

final class SubscriptionController
{
    public function index(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Session::flash('error', 'Silakan login dulu.');
            Response::redirect('/login');
            return;
        }

        $service = new SubscriptionService();
        $overview = $service->overview((int) $auth['user_id']);

        Response::html(View::render('subscription/index', [
            'user' => Session::get('auth'),
            'overview' => $overview,
            'csrf_token' => Csrf::token(),
            'flash_success' => Session::pullFlash('success'),
            'flash_error' => Session::pullFlash('error'),
        ]));
    }

    public function requestExtend(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Session::flash('error', 'Silakan login dulu.');
            Response::redirect('/login');
            return;
        }

        $planCode = strtoupper(trim((string) $request->input('plan_code', 'FREE')));
        $durationType = strtoupper(trim((string) $request->input('duration_type', 'MONTHLY')));

        $service = new SubscriptionService();
        $result = $service->requestExtend((int) $auth['user_id'], $planCode, $durationType);

        if ($result['ok']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        Response::redirect('/subscription');
    }
}

