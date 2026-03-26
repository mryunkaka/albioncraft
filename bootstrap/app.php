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
$router->get('/login', [App\Controllers\AuthController::class, 'showLogin'], [
    App\Middleware\GuestMiddleware::class,
]);
$router->post('/login', [App\Controllers\AuthController::class, 'login'], [
    App\Middleware\GuestMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->get('/register', [App\Controllers\AuthController::class, 'showRegister'], [
    App\Middleware\GuestMiddleware::class,
]);
$router->post('/register', [App\Controllers\AuthController::class, 'register'], [
    App\Middleware\GuestMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->post('/logout', [App\Controllers\AuthController::class, 'logout'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->get('/dashboard', [App\Controllers\DashboardController::class, 'index'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
]);
$router->get('/subscription', [App\Controllers\SubscriptionController::class, 'index'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
]);
$router->post('/subscription/request', [App\Controllers\SubscriptionController::class, 'requestExtend'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->get('/referral', [App\Controllers\ReferralController::class, 'index'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
]);
$router->get('/price-data', [App\Controllers\PriceDataController::class, 'index'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
    App\Middleware\PlanFeatureMiddleware::class,
]);
$router->get('/api/price-data/list', [App\Controllers\PriceDataController::class, 'list'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
    App\Middleware\PlanFeatureMiddleware::class,
]);
$router->get('/api/price-data/items', [App\Controllers\PriceDataController::class, 'items'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
    App\Middleware\PlanFeatureMiddleware::class,
]);
$router->post('/price-data/save', [App\Controllers\PriceDataController::class, 'save'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
    App\Middleware\PlanFeatureMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->post('/price-data/delete', [App\Controllers\PriceDataController::class, 'delete'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
    App\Middleware\PlanFeatureMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->get('/admin/subscription-requests', [App\Controllers\AdminSubscriptionController::class, 'index'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\AdminMiddleware::class,
]);
$router->post('/admin/subscription-requests/approve', [App\Controllers\AdminSubscriptionController::class, 'approve'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\AdminMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->post('/admin/subscription-requests/reject', [App\Controllers\AdminSubscriptionController::class, 'reject'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\AdminMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);

return $router;
