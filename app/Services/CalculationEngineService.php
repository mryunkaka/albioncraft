<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CalculationException;

final class CalculationEngineService
{
    private const DEFAULT_BONUS_BASIC = 18.0;
    private const FOCUS_BONUS = 59.0;
    private const PREMIUM_TAX_PERCENT = 0.04;
    private const NON_PREMIUM_TAX_PERCENT = 0.08;
    private const SETUP_FEE_PERCENT = 0.025;

    /**
     * @param array<string, mixed> $input
     * @return array<string, int|float|bool|array<int, array<string, int|float|string|bool>>>
     */
    public function calculate(array $input): array
    {
        $normalized = $this->normalizeAndValidate($input);
        return $this->calculateSpreadsheetStyle($normalized);
    }

    private function calculateSrp(float $productionCostPerItem, float $taxPercent, float $targetMargin): float
    {
        return $productionCostPerItem * (1 + $targetMargin) / (1 - $taxPercent - self::SETUP_FEE_PERCENT);
    }

    private function breakEvenSellPrice(float $productionCostPerItem, float $taxPercent): float
    {
        return $productionCostPerItem / (1 - $taxPercent - self::SETUP_FEE_PERCENT);
    }

    private function classifyStatusLevel(float $marginPercent): string
    {
        if ($marginPercent < 0) {
            return 'RUGI';
        }
        if ($marginPercent < 10) {
            return 'UNTUNG';
        }
        if ($marginPercent < 30) {
            return 'SEDANG';
        }
        return 'PROFIT';
    }

    /**
     * @return array{srp: float, revenue_per_item: float, profit_per_item: float, total_profit: float}
     */
    private function profitAtTargetMargin(float $productionCostPerItem, float $taxPercent, float $targetMargin, int $totalOutput): array
    {
        $srp = $this->calculateSrp($productionCostPerItem, $taxPercent, $targetMargin);
        $revenuePerItem = $srp * (1 - $taxPercent - self::SETUP_FEE_PERCENT);
        $profitPerItem = $revenuePerItem - $productionCostPerItem;
        $totalProfit = $profitPerItem * $totalOutput;

        return [
            'srp' => $srp,
            'revenue_per_item' => $revenuePerItem,
            'profit_per_item' => $profitPerItem,
            'total_profit' => $totalProfit,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{
     *   bonus_basic: float,
     *   bonus_local: float,
     *   bonus_daily: float,
     *   craft_with_focus: bool,
     *   focus_points: float,
     *   focus_per_craft: float,
     *   usage_fee: float,
     *   item_value: float,
     *   output_qty: int,
     *   target_output_qty: int,
     *   sell_price: ?float,
     *   premium_status: bool,
     *   materials: array<int, array{name: string, qty_per_recipe: float, buy_price: float, return_type: string}>
     * }
     */
    private function normalizeAndValidate(array $input): array
    {
        $errors = [];

        $bonusBasic = $this->floatValue($input['bonus_basic'] ?? self::DEFAULT_BONUS_BASIC);
        $bonusLocal = $this->floatValue($input['bonus_local'] ?? 0);
        $bonusDaily = $this->floatValue($input['bonus_daily'] ?? 0);
        $craftWithFocus = (bool) ($input['craft_with_focus'] ?? false);
        $focusPoints = $this->floatValue($input['focus_points'] ?? 0);
        $focusPerCraft = $this->floatValue($input['focus_per_craft'] ?? 0);
        $usageFee = $this->floatValue($input['usage_fee'] ?? 0);
        $itemValue = $this->floatValue($input['item_value'] ?? 0);
        $outputQty = (int) ($input['output_qty'] ?? 0);
        $targetOutputQty = (int) ($input['target_output_qty'] ?? ($input['target_craft_qty'] ?? 0));
        $sellPrice = $this->nullableFloat($input['sell_price'] ?? null);
        $premiumStatus = (bool) ($input['premium_status'] ?? false);
        $roundingMode = strtoupper(trim((string) ($input['return_rounding_mode'] ?? 'SPREADSHEET_BULK')));
        $materials = is_array($input['materials'] ?? null) ? $input['materials'] : [];

        $this->validateRange('bonus_basic', $bonusBasic, 0, 100, $errors);
        $this->validateRange('bonus_local', $bonusLocal, 0, 100, $errors);
        $this->validateRange('bonus_daily', $bonusDaily, 0, 100, $errors);
        $this->validateMin('usage_fee', $usageFee, 0, $errors);
        $this->validateMin('item_value', $itemValue, 0, $errors);
        $this->validateMin('output_qty', (float) $outputQty, 1, $errors);
        $this->validateMin('target_output_qty', (float) $targetOutputQty, 1, $errors);
        if ($sellPrice !== null) {
            $this->validateMin('sell_price', $sellPrice, 0, $errors);
        }
        $this->validateMin('focus_points', $focusPoints, 0, $errors);
        $this->validateMin('focus_per_craft', $focusPerCraft, 0, $errors);

        if ($craftWithFocus && $focusPerCraft <= 0) {
            $errors['focus_per_craft'] = 'Focus per craft must be greater than zero when focus is enabled.';
        }
        if ($craftWithFocus && $focusPerCraft > 0 && $focusPoints > 0 && $focusPoints < $focusPerCraft) {
            $errors['focus_points'] = 'Focus points must be greater than or equal to focus per craft when focus is enabled.';
        }

        if ($materials === []) {
            $errors['materials'] = 'At least one material is required.';
        }
        if (! in_array($roundingMode, ['SPREADSHEET_BULK', 'INGAME_PER_CRAFT'], true)) {
            $errors['return_rounding_mode'] = 'Invalid rounding mode.';
        }

        $normalizedMaterials = [];
        foreach ($materials as $index => $material) {
            if (! is_array($material)) {
                $errors["materials.$index"] = 'Material row must be an object-like array.';
                continue;
            }

            $name = trim((string) ($material['name'] ?? ''));
            $qtyPerRecipe = $this->floatValue($material['qty_per_recipe'] ?? 0);
            $buyPrice = $this->floatValue($material['buy_price'] ?? 0);
            $returnType = strtoupper(trim((string) ($material['return_type'] ?? '')));

            if ($name === '') {
                $errors["materials.$index.name"] = 'Material name is required.';
            }

            $this->validateMin("materials.$index.qty_per_recipe", $qtyPerRecipe, 0, $errors);
            $this->validateMin("materials.$index.buy_price", $buyPrice, 0, $errors);

            if (! in_array($returnType, ['RETURN', 'NON_RETURN'], true)) {
                $errors["materials.$index.return_type"] = 'Return type must be RETURN or NON_RETURN.';
            }

            $normalizedMaterials[] = [
                'name' => $name,
                'qty_per_recipe' => $qtyPerRecipe,
                'buy_price' => $buyPrice,
                'return_type' => $returnType,
            ];
        }

        if ($errors !== []) {
            throw new CalculationException($errors);
        }

        return [
            'bonus_basic' => $bonusBasic,
            'bonus_local' => $bonusLocal,
            'bonus_daily' => $bonusDaily,
            'return_rounding_mode' => $roundingMode,
            'craft_with_focus' => $craftWithFocus,
            'focus_points' => $focusPoints,
            'focus_per_craft' => $focusPerCraft,
            'usage_fee' => $usageFee,
            'item_value' => $itemValue,
            'output_qty' => $outputQty,
            'target_output_qty' => $targetOutputQty,
            'sell_price' => $sellPrice,
            'premium_status' => $premiumStatus,
            'materials' => $normalizedMaterials,
        ];
    }

    /**
     * @param array<string, string> $errors
     */
    private function validateRange(string $field, float $value, float $min, float $max, array &$errors): void
    {
        if ($value < $min || $value > $max) {
            $errors[$field] = sprintf('%s must be between %s and %s.', $field, $min, $max);
        }
    }

    /**
     * @param array<string, string> $errors
     */
    private function validateMin(string $field, float $value, float $min, array &$errors): void
    {
        if ($value < $min) {
            $errors[$field] = sprintf('%s must be greater than or equal to %s.', $field, $min);
        }
    }

    private function floatValue(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function round2(float $value): float
    {
        return round($value, 2);
    }

    private function round2Nullable(?float $value): ?float
    {
        if ($value === null) {
            return null;
        }
        return round($value, 2);
    }

    private function round4Nullable(?float $value): ?float
    {
        if ($value === null) {
            return null;
        }
        return round($value, 4);
    }

    private function round4(float $value): float
    {
        return round($value, 4);
    }

    /**
     * Spreadsheet behavior:
     * - Target is output items, not craft count.
     * - Estimate materials_to_buy using CEILING(qty * (target/output_qty) * (1 - rrr)) for RETURN, else *1.
     * - Simulate crafting in iterations (bulk crafts per iteration), applying integer rounding (half-up) to returned materials.
     *
     * @param array{
     *   bonus_basic: float,
     *   bonus_local: float,
     *   bonus_daily: float,
     *   craft_with_focus: bool,
     *   focus_points: float,
     *   focus_per_craft: float,
     *   usage_fee: float,
     *   item_value: float,
     *   output_qty: int,
     *   target_output_qty: int,
     *   sell_price: float,
     *   premium_status: bool,
     *   materials: array<int, array{name: string, qty_per_recipe: float, buy_price: float, return_type: string}>
     * } $normalized
     * @return array<string, int|float|bool|array<int, array<string, int|float|string|bool>>>
     */
    private function calculateSpreadsheetStyle(array $normalized): array
    {
        $bonusFocus = $normalized['craft_with_focus'] ? self::FOCUS_BONUS : 0.0;
        $totalBonus = $normalized['bonus_basic'] + $normalized['bonus_local'] + $normalized['bonus_daily'] + $bonusFocus;
        $rrr = 1 - (1 / (1 + ($totalBonus / 100)));

        if ($normalized['craft_with_focus']) {
            $focusCraftLimit = (int) floor($normalized['focus_points'] / $normalized['focus_per_craft']);
        } else {
            $focusCraftLimit = 0;
        }

        $craftsNeededFloat = $normalized['target_output_qty'] / $normalized['output_qty'];
        if ($craftsNeededFloat <= 0) {
            throw new CalculationException(['target_output_qty' => 'Target output qty must be greater than zero.']);
        }

        $materialsState = [];
        $materialCost = 0.0;

        foreach ($normalized['materials'] as $material) {
            $multiplier = $material['return_type'] === 'RETURN' ? (1 - $rrr) : 1.0;
            $toBuy = (int) ceil($material['qty_per_recipe'] * $craftsNeededFloat * $multiplier);
            $toBuy = max(0, $toBuy);

            $materialsState[] = [
                'name' => $material['name'],
                'qty_per_recipe' => $material['qty_per_recipe'],
                'buy_price' => $material['buy_price'],
                'return_type' => $material['return_type'],
                'to_buy' => $toBuy,
                'stock' => $toBuy,
            ];

            $materialCost += $toBuy * $material['buy_price'];
        }

        // Iterative simulation (bulk crafts per iteration) to mimic the spreadsheet table (fixed 20 iterations).
        $craftCount = 0;
        $iterationTrace = [];
        $maxIterations = 20;
        $focusTotalOutputLimit = $normalized['craft_with_focus'] ? ($focusCraftLimit * $normalized['output_qty']) : null;

        for ($iter = 1; $iter <= $maxIterations; $iter++) {
            $stocksSnapshot = [];
            foreach ($materialsState as $row) {
                $stocksSnapshot[] = (int) $row['stock'];
            }

            $possibleCrafts = null;
            foreach ($materialsState as $row) {
                $qty = $row['qty_per_recipe'];
                if ($qty <= 0) {
                    continue;
                }
                $craftsForMat = (int) floor($row['stock'] / $qty);
                $possibleCrafts = $possibleCrafts === null ? $craftsForMat : min($possibleCrafts, $craftsForMat);
            }

            $possibleCrafts = $possibleCrafts ?? 0;

            if ($normalized['craft_with_focus']) {
                $remainingByFocus = max(0, $focusCraftLimit - $craftCount);
                $possibleCrafts = min($possibleCrafts, $remainingByFocus);
            }

            $craftableOutput = $possibleCrafts * $normalized['output_qty'];

            if ($normalized['craft_with_focus'] && $focusTotalOutputLimit !== null) {
                $remainingFocusOutput = max(0, $focusTotalOutputLimit - ($craftCount * $normalized['output_qty']));
                $craftableOutput = min($craftableOutput, $remainingFocusOutput);
                // Convert back to crafts (must be integer crafts).
                $possibleCrafts = (int) floor($craftableOutput / $normalized['output_qty']);
                $craftableOutput = $possibleCrafts * $normalized['output_qty'];
            }

            $iterationTrace[] = [
                'iteration' => $iter,
                'stocks' => $this->padToSix($stocksSnapshot),
                'craftable_output' => (int) $craftableOutput,
            ];

            if ($possibleCrafts <= 0) {
                continue;
            }

            // Apply simulation step.
            if ($normalized['return_rounding_mode'] === 'INGAME_PER_CRAFT') {
                // Per-craft simulation for higher fidelity. Keep it bounded by possibleCrafts this iteration.
                for ($c = 0; $c < $possibleCrafts; $c++) {
                    $craftCount += 1;
                    foreach ($materialsState as $idx => $row) {
                        $consumed = $row['qty_per_recipe'];
                        $returned = 0;
                        if ($row['return_type'] === 'RETURN') {
                            $returned = (int) round($consumed * $rrr, 0, PHP_ROUND_HALF_UP);
                        }
                        $materialsState[$idx]['stock'] = $materialsState[$idx]['stock'] - $consumed + $returned;
                    }
                }
            } else {
                $craftCount += $possibleCrafts;
                foreach ($materialsState as $idx => $row) {
                    $consumed = $row['qty_per_recipe'] * $possibleCrafts;
                    $returned = 0;
                    if ($row['return_type'] === 'RETURN') {
                        $returned = (int) round($consumed * $rrr, 0, PHP_ROUND_HALF_UP);
                    }
                    $materialsState[$idx]['stock'] = $row['stock'] - $consumed + $returned;
                }
            }
        }

        if ($craftCount <= 0) {
            throw new CalculationException(['craft_count' => 'Not enough materials to craft any item.']);
        }

        $totalOutput = $craftCount * $normalized['output_qty'];
        if ($totalOutput <= 0) {
            throw new CalculationException(['total_output' => 'Total output must be greater than zero.']);
        }

        $materialReturnValue = 0.0;
        $materialBreakdown = [];

        foreach ($materialsState as $row) {
            $leftover = (float) $row['stock'];
            $lineReturnValue = $leftover * $row['buy_price'];
            $materialReturnValue += $lineReturnValue;

            $materialBreakdown[] = [
                'name' => $row['name'],
                'qty_per_recipe' => $this->round4($row['qty_per_recipe']),
                'buy_price' => $this->round2($row['buy_price']),
                'return_type' => $row['return_type'],
                'material_to_buy' => (int) $row['to_buy'],
                'leftover_qty' => (int) $leftover,
            ];
        }

        $netMaterialCost = $materialCost - $materialReturnValue;
        $craftFeePerRecipe = ($normalized['usage_fee'] * $normalized['item_value'] * $normalized['output_qty']) / 20 / (400 / 9);
        $craftFeeTotal = $craftFeePerRecipe * $craftCount;
        $productionCost = $netMaterialCost + $craftFeeTotal;
        $productionCostPerItem = $productionCost / $totalOutput;

        $taxPercent = $normalized['premium_status'] ? self::PREMIUM_TAX_PERCENT : self::NON_PREMIUM_TAX_PERCENT;

        $breakEven = $this->breakEvenSellPrice($productionCostPerItem, $taxPercent);
        $pick5 = $this->profitAtTargetMargin($productionCostPerItem, $taxPercent, 0.05, $totalOutput);
        $pick10 = $this->profitAtTargetMargin($productionCostPerItem, $taxPercent, 0.10, $totalOutput);
        $pick15 = $this->profitAtTargetMargin($productionCostPerItem, $taxPercent, 0.15, $totalOutput);
        $pick20 = $this->profitAtTargetMargin($productionCostPerItem, $taxPercent, 0.20, $totalOutput);

        $focusUsedPoints = null;
        $focusLeftPoints = null;
        $focusCanCraft = null;
        $focusTotalCraftedItem = null;

        if ($normalized['craft_with_focus']) {
            $focusCanCraft = $focusCraftLimit;
            $focusTotalCraftedItem = $focusCraftLimit * $normalized['output_qty'];
            $focusUsedPoints = $focusCraftLimit * $normalized['focus_per_craft'];
            $focusLeftPoints = max(0.0, $normalized['focus_points'] - $focusUsedPoints);
        }

        $revenuePerItem = null;
        $totalRevenue = null;
        $profitPerItem = null;
        $totalProfit = null;
        $marginPercent = null;
        $status = null;
        $statusLevel = null;

        // Always provide a scenario for profit/status:
        // - If market price is provided, use it.
        // - Otherwise default to SRP 10% as an assumed sell price, so user can still see profit/status.
        $scenarioMode = 'SRP_10_DEFAULT';
        $scenarioSellPrice = $pick10['srp'];
        $scenarioRevenuePerItem = $pick10['revenue_per_item'];
        $scenarioProfitPerItem = $pick10['profit_per_item'];
        $scenarioTotalProfit = $pick10['total_profit'];
        $scenarioMarginPercent = 10.0;

        if ($normalized['sell_price'] !== null) {
            $scenarioMode = 'MARKET';
            $scenarioSellPrice = $normalized['sell_price'];

            $taxValuePerItem = $normalized['sell_price'] * $taxPercent;
            $setupFeeValuePerItem = $normalized['sell_price'] * self::SETUP_FEE_PERCENT;
            $revenuePerItem = $normalized['sell_price'] - $taxValuePerItem - $setupFeeValuePerItem;
            $totalRevenue = $revenuePerItem * $totalOutput;
            $profitPerItem = $revenuePerItem - $productionCostPerItem;
            $totalProfit = $totalRevenue - $productionCost;
            $marginPercent = ($profitPerItem / $productionCostPerItem) * 100;

            $scenarioRevenuePerItem = $revenuePerItem;
            $scenarioProfitPerItem = $profitPerItem;
            $scenarioTotalProfit = $totalProfit;
            $scenarioMarginPercent = $marginPercent;
        }

        $status = $scenarioProfitPerItem >= 0 ? 'PROFIT' : 'RUGI';
        $statusLevel = $this->classifyStatusLevel($scenarioMarginPercent);

        return [
            'calculation_mode' => 'SPREADSHEET_SIM',
            'bonus_basic' => $this->round2($normalized['bonus_basic']),
            'bonus_local' => $this->round2($normalized['bonus_local']),
            'bonus_daily' => $this->round2($normalized['bonus_daily']),
            'bonus_focus' => $this->round2($bonusFocus),
            'total_bonus' => $this->round2($totalBonus),
            'craft_with_focus' => $normalized['craft_with_focus'],
            'focus_craft_limit' => $focusCraftLimit,
            'craft_count' => $craftCount,
            'target_output_qty' => $normalized['target_output_qty'],
            'output_qty' => $normalized['output_qty'],
            'total_output' => $totalOutput,
            'rrr' => $this->round10($rrr),
            'material_cost' => $this->round2($materialCost),
            'material_return_value' => $this->round2($materialReturnValue),
            'net_material_cost' => $this->round2($netMaterialCost),
            'craft_fee_per_recipe' => $this->round2($craftFeePerRecipe),
            'craft_fee_total' => $this->round2($craftFeeTotal),
            'production_cost' => $this->round2($productionCost),
            'production_cost_per_item' => $this->round2($productionCostPerItem),
            'tax_percent' => $this->round2($taxPercent * 100),
            'setup_fee_percent' => $this->round2(self::SETUP_FEE_PERCENT * 100),
            'sell_price' => $this->round2Nullable($normalized['sell_price']),
            'tax_value_per_item' => $this->round2Nullable(isset($taxValuePerItem) ? $taxValuePerItem : null),
            'setup_fee_value_per_item' => $this->round2Nullable(isset($setupFeeValuePerItem) ? $setupFeeValuePerItem : null),
            'revenue_per_item' => $this->round2Nullable($revenuePerItem),
            'total_revenue' => $this->round2Nullable($totalRevenue),
            'profit_per_item' => $this->round2Nullable($profitPerItem),
            'total_profit' => $this->round2Nullable($totalProfit),
            'margin_percent' => $this->round2Nullable($marginPercent),
            'status' => $status,
            'status_level' => $statusLevel,
            'scenario' => [
                'mode' => $scenarioMode,
                'sell_price' => $this->round2($scenarioSellPrice),
                'revenue_per_item' => $this->round2($scenarioRevenuePerItem),
                'profit_per_item' => $this->round2($scenarioProfitPerItem),
                'total_profit' => $this->round2($scenarioTotalProfit),
                'margin_percent' => $this->round2($scenarioMarginPercent),
            ],
            'break_even_sell_price' => $this->round2($breakEven),
            'srp_5' => $this->round2($pick5['srp']),
            'srp_10' => $this->round2($pick10['srp']),
            'srp_15' => $this->round2($pick15['srp']),
            'srp_20' => $this->round2($pick20['srp']),
            'focus' => [
                'focus_points' => $normalized['craft_with_focus'] ? (int) $normalized['focus_points'] : null,
                'focus_per_craft' => $normalized['craft_with_focus'] ? (int) $normalized['focus_per_craft'] : null,
                'sisa_focus_point' => $normalized['craft_with_focus'] ? (int) $focusLeftPoints : null,
                'kamu_bisa_craft' => $normalized['craft_with_focus'] ? (int) $focusCanCraft : null,
                'total_crafted_item' => $normalized['craft_with_focus'] ? (int) $focusTotalCraftedItem : null,
            ],
            'iterations' => $iterationTrace,
            'profit_targets' => [
                [
                    'target_margin_percent' => 5,
                    'srp' => $this->round2($pick5['srp']),
                    'profit_per_item' => $this->round2($pick5['profit_per_item']),
                    'total_profit' => $this->round2($pick5['total_profit']),
                ],
                [
                    'target_margin_percent' => 10,
                    'srp' => $this->round2($pick10['srp']),
                    'profit_per_item' => $this->round2($pick10['profit_per_item']),
                    'total_profit' => $this->round2($pick10['total_profit']),
                ],
                [
                    'target_margin_percent' => 15,
                    'srp' => $this->round2($pick15['srp']),
                    'profit_per_item' => $this->round2($pick15['profit_per_item']),
                    'total_profit' => $this->round2($pick15['total_profit']),
                ],
                [
                    'target_margin_percent' => 20,
                    'srp' => $this->round2($pick20['srp']),
                    'profit_per_item' => $this->round2($pick20['profit_per_item']),
                    'total_profit' => $this->round2($pick20['total_profit']),
                ],
            ],
            'materials' => $materialBreakdown,
            'material_fields' => $this->materialFields($materialBreakdown, $rrr, $normalized['output_qty']),
        ];
    }

    private function round10(float $value): float
    {
        return round($value, 10);
    }

    /**
     * @param array<int, int> $values
     * @return array<int, int>
     */
    private function padToSix(array $values): array
    {
        $out = [];
        for ($i = 0; $i < 6; $i++) {
            $out[] = (int) ($values[$i] ?? 0);
        }
        return $out;
    }

    /**
     * @param array<int, array{name: string, qty_per_recipe: float, buy_price: float, return_type: string, material_to_buy: int, leftover_qty: int}> $materials
     * @return array{
     *   types: array<int, string>,
     *   to_buy: array<int, int>,
     *   needed: array<int, float>,
     *   price: array<int, float>,
     *   effective_stock: array<int, int>,
     *   craftable_crafts: array<int, int>,
     *   return_material: array<int, int>
     * }
     */
    private function materialFields(array $materials, float $rrr, int $outputQty): array
    {
        $types = [];
        $toBuy = [];
        $needed = [];
        $price = [];
        $effective = [];
        $craftableCrafts = [];
        $returnMat = [];

        foreach ($materials as $m) {
            $types[] = $m['return_type'];
            $toBuy[] = (int) $m['material_to_buy'];
            $needed[] = (float) $m['qty_per_recipe'];
            $price[] = (float) $m['buy_price'];
            if ($m['return_type'] === 'RETURN') {
                $den = max(1e-9, (1 - $rrr));
                $eff = (int) round(((float) $m['material_to_buy']) / $den, 0, PHP_ROUND_HALF_UP);
            } else {
                $eff = (int) $m['material_to_buy'];
            }
            $effective[] = $eff;
            $craftableCrafts[] = $m['qty_per_recipe'] > 0 ? (int) floor($eff / $m['qty_per_recipe']) : 0;
            $returnMat[] = (int) $m['leftover_qty'];
        }

        return [
            'types' => $this->padToSixString($types),
            'to_buy' => $this->padToSix($toBuy),
            'needed' => $this->padToSixFloat($needed),
            'price' => $this->padToSixFloat($price),
            'effective_stock' => $this->padToSix($effective),
            'craftable_crafts' => $this->padToSix($craftableCrafts),
            'return_material' => $this->padToSix($returnMat),
            'output_qty' => $outputQty,
        ];
    }

    /**
     * @param array<int, float> $values
     * @return array<int, float>
     */
    private function padToSixFloat(array $values): array
    {
        $out = [];
        for ($i = 0; $i < 6; $i++) {
            $out[] = (float) ($values[$i] ?? 0.0);
        }
        return $out;
    }

    /**
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function padToSixString(array $values): array
    {
        $out = [];
        for ($i = 0; $i < 6; $i++) {
            $out[] = (string) ($values[$i] ?? '');
        }
        return $out;
    }
}
