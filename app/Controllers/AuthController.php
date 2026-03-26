<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Support\Csrf;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

final class AuthController
{
    public function showLogin(Request $request): void
    {
        if (Session::has('auth')) {
            Response::redirect('/dashboard');
            return;
        }

        Response::html(View::render('auth/login', [
            'flash_error' => Session::pullFlash('error'),
            'flash_success' => Session::pullFlash('success'),
            'errors' => Session::get('_old_errors', []),
            'old' => Session::get('_old_input', []),
            'csrf_token' => Csrf::token(),
        ]));
        Session::forget('_old_errors');
        Session::forget('_old_input');
    }

    public function login(Request $request): void
    {
        try {
            $service = new AuthService();
        } catch (\Throwable $e) {
            Response::html(View::render('errors/runtime', [
                'title' => 'Konfigurasi Belum Siap',
                'message' => $e->getMessage(),
            ]), 500);
            return;
        }
        $result = $service->login($request->post());

        if (! $result['ok']) {
            Session::put('_old_errors', $result['errors']);
            Session::put('_old_input', ['email' => (string) $request->input('email', '')]);
            Response::redirect('/login');
            return;
        }

        Session::flash('success', 'Login berhasil.');
        Response::redirect('/dashboard');
    }

    public function showRegister(Request $request): void
    {
        if (Session::has('auth')) {
            Response::redirect('/dashboard');
            return;
        }

        Response::html(View::render('auth/register', [
            'flash_error' => Session::pullFlash('error'),
            'flash_success' => Session::pullFlash('success'),
            'errors' => Session::get('_old_errors', []),
            'old' => Session::get('_old_input', []),
            'csrf_token' => Csrf::token(),
        ]));
        Session::forget('_old_errors');
        Session::forget('_old_input');
    }

    public function register(Request $request): void
    {
        try {
            $service = new AuthService();
        } catch (\Throwable $e) {
            Response::html(View::render('errors/runtime', [
                'title' => 'Konfigurasi Belum Siap',
                'message' => $e->getMessage(),
            ]), 500);
            return;
        }
        $result = $service->register($request->post());

        if (! $result['ok']) {
            Session::put('_old_errors', $result['errors']);
            Session::put('_old_input', $request->post());
            Response::redirect('/register');
            return;
        }

        Session::flash('success', 'Register berhasil. Silakan login.');
        Response::redirect('/login');
    }

    public function logout(Request $request): void
    {
        Session::destroy();
        Session::start();
        Session::flash('success', 'Logout berhasil.');
        Response::redirect('/login');
    }
}
