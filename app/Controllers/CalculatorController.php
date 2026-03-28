<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CalculationEngineService;
use App\Services\CalculationHistoryService;
use App\Services\ItemMasterService;
use App\Services\MarketPriceService;
use App\Services\RecipeAutoFillService;
use App\Support\AdminAccess;
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
        $autoFillService = new RecipeAutoFillService();
        $recipeCities = $autoFillService->cityOptions();

        Response::html(View::render('calculator/index', [
            'auth' => $auth,
            'is_admin' => is_array($auth) ? AdminAccess::isAdminEmail((string) ($auth['email'] ?? '')) : false,
            'csrf_token' => Csrf::token(),
            'recipe_cities' => $recipeCities,
        ]));
    }

    public function calculate(Request $request): void
    {
        $payload = $request->json();
        $service = new CalculationEngineService();
        $masterSync = null;

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
                $itemMasterService = new ItemMasterService();
                $masterSync = $itemMasterService->syncCalculatorInputMasterData((int) $auth['user_id'], $payload);
                if (($masterSync['ok'] ?? false) === true && is_array($masterSync['data'] ?? null)) {
                    $syncedItem = is_array($masterSync['data']['item'] ?? null) ? $masterSync['data']['item'] : null;
                    if ($syncedItem !== null && isset($syncedItem['id'])) {
                        $payload['item_id'] = (int) $syncedItem['id'];
                    }

                    $syncedMaterials = is_array($masterSync['data']['materials'] ?? null) ? $masterSync['data']['materials'] : [];
                    if (is_array($payload['materials'] ?? null)) {
                        foreach ($payload['materials'] as $index => $material) {
                            if (! is_array($material)) {
                                continue;
                            }

                            $syncedMaterial = is_array($syncedMaterials[$index] ?? null) ? $syncedMaterials[$index] : null;
                            if ($syncedMaterial === null) {
                                continue;
                            }

                            $payload['materials'][$index]['item_id'] = (int) ($syncedMaterial['id'] ?? 0);
                            $payload['materials'][$index]['item_value'] = (float) ($syncedMaterial['item_value'] ?? 0);
                        }
                    }
                }
            } catch (\Throwable) {
                // Sync master item tidak boleh memblokir response calculator.
            }
        }

        try {
            $autoFillService = new RecipeAutoFillService();
            $autoFillService->storeCalculatedRecipe(is_array($auth) ? $auth : null, $payload);
        } catch (\Throwable) {
            // Library recipe kalkulator tidak boleh memblokir response calculator.
        }

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
            'meta' => [
                'master_sync' => $masterSync,
            ],
        ]);
    }

    public function recipeItems(Request $request): void
    {
        $auth = Session::get('auth');
        $service = new RecipeAutoFillService();
        $q = (string) $request->input('q', '');
        Response::json([
            'success' => true,
            'data' => $service->itemOptions($q, is_array($auth) ? $auth : null),
        ]);
    }

    public function recipeDetail(Request $request): void
    {
        $auth = Session::get('auth');
        $entryReference = trim((string) $request->input('entry_id', (string) $request->input('item_id', '')));
        $cityId = (int) $request->input('city_id', 0);

        $service = new RecipeAutoFillService();
        $result = $service->recipeDetail($entryReference, $cityId > 0 ? $cityId : null, is_array($auth) ? $auth : null);

        Response::json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function itemSearch(Request $request): void
    {
        $service = new MarketPriceService();
        $q = (string) $request->input('q', '');

        Response::json([
            'success' => true,
            'data' => $service->itemOptions($q),
        ]);
    }

    public function csrfToken(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Response::json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
            return;
        }

        Response::json([
            'success' => true,
            'data' => [
                'csrf_token' => Csrf::token(),
            ],
        ]);
    }

    public function persistSelectionHelper(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $service = new ItemMasterService();
        $result = $service->persistSelectionHelper((int) $auth['user_id'], $request->post());

        Response::json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => $result['data'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    public function saveCraftFee(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $payload = $request->post();
        $payload['price_type'] = 'CRAFT_FEE';
        $payload['price_value'] = $request->input('usage_fee', 0);
        $service = new MarketPriceService();
        $result = $service->upsertPrice((int) $auth['user_id'], $payload);

        Response::json([
            'success' => $result['ok'],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }

    public function saveSellPrice(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $payload = $request->post();
        $payload['price_type'] = 'SELL';
        $payload['price_value'] = $request->input('sell_price', 0);
        $service = new MarketPriceService();
        $result = $service->upsertPrice((int) $auth['user_id'], $payload);

        Response::json([
            'success' => $result['ok'],
            'message' => $result['message'],
        ], $result['ok'] ? 200 : 422);
    }

    public function saveMaterialPrices(Request $request): void
    {
        $auth = Session::get('auth');
        if (! is_array($auth) || ! isset($auth['user_id'])) {
            Response::json(['success' => false, 'message' => 'Unauthorized'], 401);
            return;
        }

        $materials = $request->post()['materials'] ?? [];
        if (! is_array($materials) || $materials === []) {
            Response::json(['success' => false, 'message' => 'Material market price belum diisi.'], 422);
            return;
        }

        $service = new MarketPriceService();
        $savedCount = 0;

        foreach ($materials as $row) {
            if (! is_array($row)) {
                continue;
            }

            $itemId = (int) ($row['item_id'] ?? 0);
            $cityId = trim((string) ($row['city_id'] ?? ''));
            $priceValue = $row['buy_price'] ?? 0;

            if ($itemId <= 0 || $cityId === '') {
                continue;
            }

            $result = $service->upsertPrice((int) $auth['user_id'], [
                'item_id' => $itemId,
                'city_id' => $cityId,
                'price_type' => 'BUY',
                'price_value' => $priceValue,
            ]);

            if (! $result['ok']) {
                Response::json([
                    'success' => false,
                    'message' => $result['message'],
                ], 422);
                return;
            }

            $savedCount++;
        }

        if ($savedCount <= 0) {
            Response::json(['success' => false, 'message' => 'Pilih minimal satu kota material untuk disimpan.'], 422);
            return;
        }

        Response::json([
            'success' => true,
            'message' => sprintf('%d harga material berhasil disimpan.', $savedCount),
        ]);
    }
}
