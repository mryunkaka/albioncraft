<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Services\RecipeAutoFillService;
use App\Support\Env;

Env::load(dirname(__DIR__) . '/.env');

$failures = [];

/**
 * @param list<string> $failures
 */
function expectOk(bool $condition, string $message, array &$failures): void
{
    if (! $condition) {
        $failures[] = $message;
    }
}

try {
    $service = new RecipeAutoFillService();

    $items = $service->itemOptions('');
    expectOk(count($items) >= 3, 'Item options recipe harus mengembalikan minimal 3 sample seed.', $failures);

    $leatherT3 = null;
    $potion = null;
    $helmet = null;
    foreach ($items as $item) {
        if (($item['item_code'] ?? '') === 'LEATHER_T3') {
            $leatherT3 = $item;
        }
        if (($item['item_code'] ?? '') === 'T4_POTION_SAMPLE') {
            $potion = $item;
        }
        if (($item['item_code'] ?? '') === 'LEATHER_HELMET_T4') {
            $helmet = $item;
        }
    }

    expectOk(is_array($leatherT3), 'Sample LEATHER_T3 tidak ditemukan di recipe item options.', $failures);
    expectOk(is_array($potion), 'Sample T4_POTION_SAMPLE tidak ditemukan di recipe item options.', $failures);
    expectOk(is_array($helmet), 'Sample LEATHER_HELMET_T4 tidak ditemukan di recipe item options.', $failures);

    if (is_array($leatherT3)) {
        $detail = $service->recipeDetail((int) $leatherT3['id'], null);
        expectOk($detail['ok'] === true, 'Recipe detail LEATHER_T3 harus sukses.', $failures);
        $data = $detail['data'] ?? [];
        $materials = is_array($data['materials'] ?? null) ? $data['materials'] : [];
        expectOk((int) (($data['item']['output_qty'] ?? 0)) === 1, 'Output qty LEATHER_T3 harus 1.', $failures);
        expectOk(count($materials) === 2, 'Material LEATHER_T3 harus 2.', $failures);
        expectOk((float) (($data['city_bonus']['bonus_percent'] ?? 0)) === 0.0, 'Tanpa city, bonus local harus 0.', $failures);
    }

    if (is_array($potion)) {
        $detail = $service->recipeDetail((int) $potion['id'], 1);
        expectOk($detail['ok'] === true, 'Recipe detail potion harus sukses.', $failures);
        $data = $detail['data'] ?? [];
        expectOk((int) (($data['item']['output_qty'] ?? 0)) === 10, 'Output qty potion sample harus 10.', $failures);
        expectOk((float) (($data['city_bonus']['bonus_percent'] ?? -1)) === 15.0, 'Bonus city potion Brecilien harus 15.', $failures);
    }

    if (is_array($helmet)) {
        $detail = $service->recipeDetail((int) $helmet['id'], 5);
        expectOk($detail['ok'] === true, 'Recipe detail helmet harus sukses.', $failures);
        $data = $detail['data'] ?? [];
        $materials = is_array($data['materials'] ?? null) ? $data['materials'] : [];
        expectOk((float) (($data['city_bonus']['bonus_percent'] ?? -1)) === 15.0, 'Bonus city helmet Lymhurst harus 15.', $failures);
        expectOk(
            isset($materials[1]['return_type']) && (string) $materials[1]['return_type'] === 'NON_RETURN',
            'Material kedua helmet harus NON_RETURN.',
            $failures
        );
    }
} catch (\Throwable $e) {
    $failures[] = 'Unhandled exception: ' . $e->getMessage();
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED\n" . implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "PASS\n";
