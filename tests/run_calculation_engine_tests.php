<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Services\CalculationEngineService;
use App\Support\CalculationException;

$service = new CalculationEngineService();

$tests = [
    [
        'name' => 'case1_refining_leather_t3',
        'input' => [
            'bonus_basic' => 18,
            'bonus_local' => 40,
            'bonus_daily' => 0,
            'craft_with_focus' => false,
            'usage_fee' => 200,
            'item_value' => 64,
            'output_qty' => 1,
            'target_output_qty' => 100,
            'sell_price' => 300,
            'premium_status' => true,
            'materials' => [
                ['name' => 'Hide T3', 'qty_per_recipe' => 2, 'buy_price' => 100, 'return_type' => 'RETURN'],
                ['name' => 'Leather T2', 'qty_per_recipe' => 1, 'buy_price' => 80, 'return_type' => 'RETURN'],
            ],
        ],
        'expected' => [
            'rrr' => 0.3670886076,
            'total_output' => 99,
            'material_cost' => 17820.00,
            'material_return_value' => 200.00,
            'net_material_cost' => 17620.00,
            'craft_fee_total' => 1425.60,
            'production_cost' => 19045.60,
            'production_cost_per_item' => 192.38,
            'revenue_per_item' => 280.50,
            'profit_per_item' => 88.12,
            'total_profit' => 8723.90,
            'margin_percent' => 45.81,
        ],
    ],
    [
        'name' => 'case2_potion_output_x10',
        'input' => [
            'bonus_basic' => 18,
            'bonus_local' => 15,
            'bonus_daily' => 10,
            'craft_with_focus' => false,
            'usage_fee' => 250,
            'item_value' => 180,
            'output_qty' => 10,
            'target_output_qty' => 500,
            'sell_price' => 1200,
            'premium_status' => true,
            'materials' => [
                ['name' => 'Teasel', 'qty_per_recipe' => 4, 'buy_price' => 200, 'return_type' => 'RETURN'],
                ['name' => 'Goose Egg', 'qty_per_recipe' => 2, 'buy_price' => 150, 'return_type' => 'NON_RETURN'],
            ],
        ],
        'expected' => [
            'rrr' => 0.3006993007,
            'total_output' => 490,
            'material_cost' => 43000.00,
            'material_return_value' => 900.00,
            'net_material_cost' => 42100.00,
            'craft_fee_total' => 24806.25,
            'production_cost' => 66906.25,
            'production_cost_per_item' => 136.54,
            'revenue_per_item' => 1122.00,
            'profit_per_item' => 985.46,
            'total_profit' => 482873.75,
            'margin_percent' => 721.72,
        ],
    ],
    [
        'name' => 'case3_equipment_non_return',
        'input' => [
            'bonus_basic' => 18,
            'bonus_local' => 15,
            'bonus_daily' => 0,
            'craft_with_focus' => false,
            'usage_fee' => 300,
            'item_value' => 240,
            'output_qty' => 1,
            'target_output_qty' => 100,
            'sell_price' => 5000,
            'premium_status' => true,
            'materials' => [
                ['name' => 'Leather T3', 'qty_per_recipe' => 8, 'buy_price' => 200, 'return_type' => 'RETURN'],
                ['name' => 'Artifact', 'qty_per_recipe' => 1, 'buy_price' => 2000, 'return_type' => 'NON_RETURN'],
            ],
        ],
        'expected' => [
            'rrr' => 0.2481203008,
            'material_cost' => 320400.00,
            'material_return_value' => 3400.00,
            'net_material_cost' => 317000.00,
            'craft_fee_total' => 8019.00,
            'production_cost' => 325019.00,
            'production_cost_per_item' => 3283.02,
            'revenue_per_item' => 4675.00,
            'profit_per_item' => 1391.98,
            'total_profit' => 137806.00,
            'margin_percent' => 42.40,
        ],
    ],
    [
        'name' => 'case4_focus_enabled',
        'input' => [
            'bonus_basic' => 18,
            'bonus_local' => 15,
            'bonus_daily' => 10,
            'craft_with_focus' => true,
            'focus_points' => 30000,
            'focus_per_craft' => 5000,
            'usage_fee' => 200,
            'item_value' => 180,
            'output_qty' => 1,
            'target_output_qty' => 20,
            'sell_price' => 2000,
            'premium_status' => true,
            'materials' => [
                ['name' => 'Material A', 'qty_per_recipe' => 5, 'buy_price' => 300, 'return_type' => 'RETURN'],
            ],
        ],
        'expected' => [
            'focus_craft_limit' => 6,
            'craft_count' => 6,
            'total_output' => 6,
            'rrr' => 0.5049504950,
            'material_cost' => 15000.00,
            'material_return_value' => 10500.00,
            'production_cost' => 4743.00,
        ],
    ],
    [
        'name' => 'case5_non_premium_tax',
        'input' => [
            'bonus_basic' => 18,
            'bonus_local' => 0,
            'bonus_daily' => 0,
            'craft_with_focus' => false,
            'usage_fee' => 1,
            'item_value' => 1,
            'output_qty' => 1,
            'target_output_qty' => 1,
            'sell_price' => 1000,
            'premium_status' => false,
            'materials' => [
                ['name' => 'Placeholder', 'qty_per_recipe' => 1, 'buy_price' => 1, 'return_type' => 'RETURN'],
            ],
        ],
        'expected' => [
            'tax_percent' => 8.00,
            'setup_fee_percent' => 2.50,
            'revenue_per_item' => 895.00,
        ],
    ],
    [
        'name' => 'case6_srp_validation',
        'type' => 'srp_formula_only',
        'expected' => [
            'srp_10' => 117.65,
        ],
    ],
    [
        'name' => 'case7_spreadsheet_screenshot_like',
        'input' => [
            'bonus_basic' => 18,
            'bonus_local' => 30,
            'bonus_daily' => 0,
            'craft_with_focus' => false,
            'usage_fee' => 300,
            'item_value' => 240,
            'output_qty' => 5,
            'target_output_qty' => 20,
            'sell_price' => 1850,
            'premium_status' => true,
            'materials' => [
                ['name' => 'Crenellated Burdock 4', 'qty_per_recipe' => 24, 'buy_price' => 300, 'return_type' => 'RETURN'],
                ['name' => 'Telur Ayam 3', 'qty_per_recipe' => 6, 'buy_price' => 100, 'return_type' => 'RETURN'],
            ],
        ],
        'expected' => [
            'rrr' => 0.3243243243,
            'craft_count' => 3,
            'total_output' => 15,
            'material_cost' => 21200.00,
            'material_return_value' => 5600.00,
            'craft_fee_total' => 1215.00,
            'production_cost' => 16815.00,
            'production_cost_per_item' => 1121.00,
            'profit_per_item' => 608.75,
            'total_profit' => 9131.25,
            'margin_percent' => 54.30,
            'srp_10' => 1318.82,
        ],
    ],
];

$failures = [];

foreach ($tests as $test) {
    try {
        if (($test['type'] ?? null) === 'srp_formula_only') {
            $result = ['srp_10' => round(100 * 1.10 / (1 - 0.04 - 0.025), 2)];
        } else {
            $result = $service->calculate($test['input']);
        }
    } catch (CalculationException $exception) {
        $failures[] = sprintf('%s threw exception: %s (%s)', $test['name'], $exception->getMessage(), json_encode($exception->errors()));
        continue;
    } catch (Throwable $exception) {
        $failures[] = sprintf('%s crashed: %s', $test['name'], $exception->getMessage());
        continue;
    }

    foreach ($test['expected'] as $field => $expectedValue) {
        $actualValue = $result[$field] ?? null;

        if (is_int($expectedValue)) {
            if ($actualValue !== $expectedValue) {
                $failures[] = sprintf('%s field %s expected %s got %s', $test['name'], $field, (string) $expectedValue, var_export($actualValue, true));
            }
            continue;
        }

        $expectedFloat = (float) $expectedValue;
        $actualFloat = (float) $actualValue;
        $maxDeviation = max(abs($expectedFloat) * 0.001, 0.02);

        if (abs($actualFloat - $expectedFloat) > $maxDeviation) {
            $failures[] = sprintf(
                '%s field %s expected %.4f got %.4f (allowed deviation %.4f)',
                $test['name'],
                $field,
                $expectedFloat,
                $actualFloat,
                $maxDeviation
            );
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED\n" . implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "PASS\n";
