<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = []): string
    {
        $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $view) . '.php';

        if (! is_file($path)) {
            throw new RuntimeException('View tidak ditemukan: ' . $view);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        $content = ob_get_clean();

        return is_string($content) ? $content : '';
    }
}

