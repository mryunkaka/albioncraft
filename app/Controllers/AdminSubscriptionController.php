<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SubscriptionService;
use App\Support\Csrf;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

final class AdminSubscriptionController
{
    public function index(Request $request): void
    {
        $service = new SubscriptionService();
        $rows = $service->pendingRequests(200);

        Response::html(View::render('admin/subscription-requests', [
            'rows' => $rows,
            'csrf_token' => Csrf::token(),
            'flash_success' => Session::pullFlash('success'),
            'flash_error' => Session::pullFlash('error'),
        ]));
    }

    public function actions(Request $request): void
    {
        $service = new SubscriptionService();
        $history = $service->adminActionHistory($request->query());

        Response::html(View::render('admin/subscription-actions', [
            'history' => $history,
            'flash_success' => Session::pullFlash('success'),
            'flash_error' => Session::pullFlash('error'),
        ]));
    }

    public function approve(Request $request): void
    {
        $id = (int) $request->input('request_action_id', 0);
        $auth = Session::get('auth');
        $actor = is_array($auth) ? (string) ($auth['email'] ?? 'ADMIN') : 'ADMIN';

        $service = new SubscriptionService();
        $result = $service->approveRequest($id, $actor);

        if ($result['ok']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }
        Response::redirect('/admin/subscription-requests');
    }

    public function reject(Request $request): void
    {
        $id = (int) $request->input('request_action_id', 0);
        $auth = Session::get('auth');
        $actor = is_array($auth) ? (string) ($auth['email'] ?? 'ADMIN') : 'ADMIN';

        $service = new SubscriptionService();
        $result = $service->rejectRequest($id, $actor);

        if ($result['ok']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }
        Response::redirect('/admin/subscription-requests');
    }
}
