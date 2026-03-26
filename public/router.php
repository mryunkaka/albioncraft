<?php

declare(strict_types=1);

// Router for PHP built-in server:
// php -S 127.0.0.1:8000 -t public public/router.php
// If the requested resource exists as a real file, serve it directly.

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? $path : '/';
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';

