<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CalculationEngineService;
use App\Support\CalculationException;
use App\Support\Request;
use App\Support\Response;

final class CalculatorController
{
    public function index(Request $request): void
    {
        $viewPath = dirname(__DIR__) . '/Views/calculator/index.php';

        ob_start();
        require $viewPath;
        $html = (string) ob_get_clean();

        Response::html($html);
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

        Response::json([
            'success' => true,
            'data' => $result,
        ]);
    }
}

