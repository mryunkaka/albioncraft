<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;
use App\Support\Csrf;

final class DashboardController
{
    public function index(Request $request): void
    {
        if (! Session::has('auth')) {
            Session::flash('error', 'Silakan login dulu.');
            Response::redirect('/login');
            return;
        }

        Response::html(View::render('dashboard/index', [
            'user' => Session::get('auth'),
            'csrf_token' => Csrf::token(),
            'flash_success' => Session::pullFlash('success'),
            'flash_error' => Session::pullFlash('error'),
        ]));
    }
}
