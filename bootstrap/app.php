<?php

declare(strict_types=1);

require __DIR__ . '/autoload.php';

use App\Services\AuthSessionService;
use App\Support\Router;
use App\Support\Env;
use App\Support\Session;

Env::load(dirname(__DIR__) . '/.env');
Session::start();
(new AuthSessionService())->bootstrap();

set_exception_handler(static function (Throwable $exception): void {
    $root = dirname(__DIR__);
    $debug = Env::get('APP_DEBUG', '0') === '1';
    $logDir = $root . '/storage/logs';
    $logFile = $logDir . '/app.log';

    if (! is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $line = sprintf(
        "[%s] %s: %s in %s:%d\nStack: %s\n\n",
        date('Y-m-d H:i:s'),
        get_class($exception),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTraceAsString()
    );
    @file_put_contents($logFile, $line, FILE_APPEND);

    if (PHP_SAPI === 'cli') {
        throw $exception;
    }

    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    if ($debug) {
        echo "Internal Server Error\n";
        echo $exception->getMessage() . "\n";
        echo $exception->getFile() . ':' . $exception->getLine() . "\n";
    } else {
        echo 'Internal Server Error';
    }
});

$router = new Router();

// Routes
$router->get('/', [App\Controllers\CalculatorController::class, 'index']);
$router->get('/calculator', [App\Controllers\CalculatorController::class, 'index']);
$router->post('/api/calculate', [App\Controllers\CalculatorController::class, 'calculate'], [
    App\Middleware\ApiRateLimitMiddleware::class,
]);
$router->get('/api/calculator/recipes/items', [App\Controllers\CalculatorController::class, 'recipeItems']);
$router->get('/api/calculator/recipes/detail', [App\Controllers\CalculatorController::class, 'recipeDetail']);
$router->get('/api/calculator/items/search', [App\Controllers\CalculatorController::class, 'itemSearch']);
$router->get('/api/calculator/csrf-token', [App\Controllers\CalculatorController::class, 'csrfToken'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
]);
$router->post('/api/calculator/helper/persist', [App\Controllers\CalculatorController::class, 'persistSelectionHelper'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->post('/api/calculator/craft-fee/save', [App\Controllers\CalculatorController::class, 'saveCraftFee'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->post('/api/calculator/sell-price/save', [App\Controllers\CalculatorController::class, 'saveSellPrice'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->post('/api/calculator/material-prices/save', [App\Controllers\CalculatorController::class, 'saveMaterialPrices'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->get('/login', [App\Controllers\AuthController::class, 'showLogin'], [
    App\Middleware\GuestMiddleware::class,
]);
$router->post('/login', [App\Controllers\AuthController::class, 'login'], [
    App\Middleware\GuestMiddleware::class,
    App\Middleware\AuthRateLimitMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->get('/register', [App\Controllers\AuthController::class, 'showRegister'], [
    App\Middleware\GuestMiddleware::class,
]);
$router->post('/register', [App\Controllers\AuthController::class, 'register'], [
    App\Middleware\GuestMiddleware::class,
    App\Middleware\AuthRateLimitMiddleware::class,
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
$router->post('/price-data/bulk-save', [App\Controllers\PriceDataController::class, 'bulkSave'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\SubscriptionMiddleware::class,
    App\Middleware\PlanFeatureMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->get('/admin/subscription-requests', [App\Controllers\AdminSubscriptionController::class, 'index'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\AdminMiddleware::class,
]);
$router->get('/admin/subscription-actions', [App\Controllers\AdminSubscriptionController::class, 'actions'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\AdminMiddleware::class,
]);
$router->get('/admin/users', [App\Controllers\AdminUserManagementController::class, 'index'], [
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
$router->post('/admin/users/profile', [App\Controllers\AdminUserManagementController::class, 'updateProfile'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\AdminMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->post('/admin/users/password', [App\Controllers\AdminUserManagementController::class, 'updatePassword'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\AdminMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->post('/admin/users/plan', [App\Controllers\AdminUserManagementController::class, 'updatePlan'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\AdminMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);
$router->post('/admin/users/delete-permanent', [App\Controllers\AdminUserManagementController::class, 'deletePermanent'], [
    App\Middleware\AuthMiddleware::class,
    App\Middleware\AdminMiddleware::class,
    App\Middleware\CsrfMiddleware::class,
]);

// Endpoint debug/setup hanya aktif saat APP_DEBUG=1.
if (Env::get('APP_DEBUG', '0') === '1') {
    $router->get('/debug-db', [App\Controllers\DebugController::class, 'db']);
    $router->get('/setup/seed', [App\Controllers\SetupController::class, 'seed']);
}

return $router;
