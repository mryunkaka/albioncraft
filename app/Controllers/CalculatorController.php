<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CalculationEngineService;
use App\Services\CalculationHistoryService;
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
        Response::html(View::render('calculator/index', [
            'auth' => Session::get('auth'),
            'csrf_token' => Csrf::token(),
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
}
