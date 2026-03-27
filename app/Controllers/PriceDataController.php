<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MarketPriceService;
use App\Support\Csrf;
use App\Support\AdminAccess;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

final class PriceDataController
{
    public function index(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Session::flash('error', 'Silakan login dulu.');
            Response::redirect('/login');
            return;
        }

        $service = new MarketPriceService();

        Response::html(View::render('price-data/index', [
            'auth' => $auth,
            'user' => $auth,
            'cities' => $service->cityOptions(),
            'item_options' => $service->itemOptions(),
            'is_admin' => AdminAccess::isAdminEmail((string) ($auth['email'] ?? '')),
            'csrf_token' => Csrf::token(),
            'flash_success' => Session::pullFlash('success'),
            'flash_error' => Session::pullFlash('error'),
        ]));
    }

    public function list(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $service = new MarketPriceService();
        $data = $service->listByUser((int) $auth['user_id'], $request->query());

        Response::json(['success' => true, 'data' => $data]);
    }

    public function save(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Response::redirect('/login');
            return;
        }

        $service = new MarketPriceService();
        $result = $service->upsertPrice((int) $auth['user_id'], $request->post());

        if ($request->isAjax()) {
            Response::json([
                'success' => $result['ok'],
                'message' => $result['message'],
            ], $result['ok'] ? 200 : 422);
            return;
        }

        if ($result['ok']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }
        Response::redirect('/price-data');
    }

    public function delete(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $id = (int) $request->input('id', 0);
        $service = new MarketPriceService();
        $result = $service->deletePrice((int) $auth['user_id'], $id);

        if ($request->isAjax()) {
            Response::json([
                'success' => $result['ok'],
                'message' => $result['message'],
            ], $result['ok'] ? 200 : 422);
            return;
        }

        if ($result['ok']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }
        Response::redirect('/price-data');
    }

    public function items(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $service = new MarketPriceService();
        $q = (string) $request->input('q', '');
        $rows = $service->itemOptions($q);
        Response::json(['success' => true, 'data' => $rows]);
    }

    public function bulkSave(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Response::redirect('/login');
            return;
        }

        $service = new MarketPriceService();
        $result = $service->bulkUpsertPrices((int) $auth['user_id'], $request->post());

        if ($request->isAjax()) {
            Response::json([
                'success' => $result['ok'],
                'message' => $result['message'],
                'data' => [
                    'created_count' => $result['created_count'] ?? 0,
                    'updated_count' => $result['updated_count'] ?? 0,
                    'error_count' => $result['error_count'] ?? 0,
                    'errors' => $result['errors'] ?? [],
                ],
            ], $result['ok'] ? 200 : 422);
            return;
        }

        if ($result['ok']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }
        Response::redirect('/price-data');
    }
}
