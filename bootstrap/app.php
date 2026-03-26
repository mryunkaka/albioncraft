<?php

declare(strict_types=1);

require __DIR__ . '/autoload.php';

use App\Support\Router;
use App\Support\Env;
use App\Support\Session;

Env::load(dirname(__DIR__) . '/.env');
Session::start();

set_exception_handler(static function (Throwable $exception): void {
    // Fallback error handler. For now, avoid leaking internals.
    if (PHP_SAPI === 'cli') {
        throw $exception;
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Internal Server Error';
});

$router = new Router();

// Routes
$router->get('/', [App\Controllers\CalculatorController::class, 'index']);
$router->get('/calculator', [App\Controllers\CalculatorController::class, 'index']);
$router->post('/api/calculate', [App\Controllers\CalculatorController::class, 'calculate']);
$router->get('/login', [App\Controllers\AuthController::class, 'showLogin']);
$router->post('/login', [App\Controllers\AuthController::class, 'login']);
$router->get('/register', [App\Controllers\AuthController::class, 'showRegister']);
$router->post('/register', [App\Controllers\AuthController::class, 'register']);
$router->post('/logout', [App\Controllers\AuthController::class, 'logout']);
$router->get('/dashboard', [App\Controllers\DashboardController::class, 'index']);

return $router;
