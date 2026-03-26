<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Support\AuthRateLimiter;
use App\Support\Csrf;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

final class AuthController
{
    /**
     * @param array<string, mixed> $errors
     * @return array<int, string>
     */
    private function errorSummary(array $errors): array
    {
        $summary = [];
        foreach ($errors as $message) {
            if (is_string($message) && $message !== '') {
                $summary[] = $message;
            }
        }

        return array_values(array_unique($summary));
    }

    /**
     * @param array<string, mixed> $input
     * @param array<int, string> $allowedKeys
     * @return array<string, string>
     */
    private function oldInput(array $input, array $allowedKeys): array
    {
        $result = [];
        foreach ($allowedKeys as $key) {
            $result[$key] = trim((string) ($input[$key] ?? ''));
        }

        return $result;
    }

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
            'error_summary' => Session::get('_old_error_summary', []),
            'old' => Session::get('_old_input', []),
            'csrf_token' => Csrf::token(),
        ]));
        Session::forget('_old_errors');
        Session::forget('_old_error_summary');
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
            $rate = AuthRateLimiter::hit('login', $request);
            if (! $rate['allowed']) {
                $retryAfter = max(1, (int) $rate['retry_after']);
                Session::flash('error', "Terlalu banyak percobaan login. Coba lagi dalam {$retryAfter} detik.");
            }
            Session::put('_old_errors', $result['errors']);
            Session::put('_old_error_summary', $this->errorSummary($result['errors']));
            Session::put('_old_input', $this->oldInput($request->post(), ['email']));
            Response::redirect('/login');
            return;
        }

        AuthRateLimiter::clear('login', $request);
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
            'error_summary' => Session::get('_old_error_summary', []),
            'old' => Session::get('_old_input', []),
            'csrf_token' => Csrf::token(),
        ]));
        Session::forget('_old_errors');
        Session::forget('_old_error_summary');
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
            $rate = AuthRateLimiter::hit('register', $request);
            if (! $rate['allowed']) {
                $retryAfter = max(1, (int) $rate['retry_after']);
                Session::flash('error', "Terlalu banyak percobaan register. Coba lagi dalam {$retryAfter} detik.");
            }
            Session::put('_old_errors', $result['errors']);
            Session::put('_old_error_summary', $this->errorSummary($result['errors']));
            Session::put('_old_input', $this->oldInput($request->post(), ['username', 'email', 'referral_code']));
            Response::redirect('/register');
            return;
        }

        AuthRateLimiter::clear('register', $request);
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
