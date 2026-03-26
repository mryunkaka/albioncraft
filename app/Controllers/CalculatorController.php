<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CalculationEngineService;
use App\Services\CalculationHistoryService;
use App\Services\RecipeAutoFillService;
use App\Services\SubscriptionService;
use App\Support\CalculationException;
use App\Support\Csrf;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\View;

final class CalculatorController
{
    public function index(Request $request): void
    {
        $auth = Session::get('auth');
        $recipeAutoFillEnabled = false;
        $recipeCities = [];

        if (is_array($auth) && isset($auth['user_id'])) {
            $subscriptionService = new SubscriptionService();
            $recipeAutoFillEnabled = $subscriptionService->userCanAccessFeature(
                (int) $auth['user_id'],
                'recipe_auto_fill'
            );

            if ($recipeAutoFillEnabled) {
                $autoFillService = new RecipeAutoFillService();
                $recipeCities = $autoFillService->cityOptions();
            }
        }

        Response::html(View::render('calculator/index', [
            'auth' => $auth,
            'csrf_token' => Csrf::token(),
            'recipe_auto_fill_enabled' => $recipeAutoFillEnabled,
            'recipe_cities' => $recipeCities,
        ]));
    }

    public function calculate(Request $request): void
    {
        $payload = $request->json();
        $service = new CalculationEngineService();

        try {
            $result = $service->calculate($payload);
        } catch (CalculationException $exception) {
            Response::json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $exception->errors(),
            ], 422);
            return;
        } catch (\Throwable $exception) {
            Response::json([
                'success' => false,
                'message' => 'Internal error.',
            ], 500);
            return;
        }

        $auth = Session::get('auth');
        if (is_array($auth) && isset($auth['user_id'])) {
            try {
                $historyService = new CalculationHistoryService();
                $historyService->store($auth, $payload, $result);
            } catch (\Throwable) {
                // Histori kalkulasi tidak boleh memblokir response calculator.
            }
        }

        Response::json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function recipeItems(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $service = new RecipeAutoFillService();
        $q = (string) $request->input('q', '');
        Response::json([
            'success' => true,
            'data' => $service->itemOptions($q),
        ]);
    }

    public function recipeDetail(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $itemId = (int) $request->input('item_id', 0);
        $cityId = (int) $request->input('city_id', 0);

        $service = new RecipeAutoFillService();
        $result = $service->recipeDetail($itemId, $cityId > 0 ? $cityId : null, (int) $auth['user_id']);

        Response::json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }
}
