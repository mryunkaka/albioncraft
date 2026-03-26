<?php

declare(strict_types=1);

namespace App\Support;

final class Request
{
    /**
     * @param mixed $default
     * @return mixed
     */
    public function input(string $key, $default = null)
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function post(): array
    {
        return is_array($_POST) ? $_POST : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return is_array($_GET) ? $_GET : [];
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        if ($path === '') {
            $path = '/';
        }

        // Normalize trailing slash except root.
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (! str_contains($contentType, 'application/json')) {
            return [];
        }

        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function isAjax(): bool
    {
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return $requestedWith === 'xmlhttprequest';
    }
}
