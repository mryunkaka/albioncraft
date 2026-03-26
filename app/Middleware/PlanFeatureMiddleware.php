<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\SubscriptionService;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;

final class PlanFeatureMiddleware implements MiddlewareInterface
{
    /**
     * @var array<string, string>
     */
    private const FEATURE_BY_PATH = [
        '/price-data' => 'price_bulk_input',
    ];

    public function handle(Request $request): bool
    {
        $featureKey = self::FEATURE_BY_PATH[$request->path()] ?? null;
        if ($featureKey === null) {
            return true;
        }

        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Session::flash('error', 'Silakan login dulu.');
            Response::redirect('/login');
            return false;
        }

        $service = new SubscriptionService();
        $allowed = $service->userCanAccessFeature((int) $auth['user_id'], $featureKey);
        if ($allowed) {
            return true;
        }

        Session::flash('error', 'Fitur ini membutuhkan plan yang lebih tinggi.');
        Response::redirect('/dashboard');
        return false;
    }
}

