<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Router
{
    /** @var array<string, array<string, array{0: class-string, 1: string}>> */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][$this->normalizePath($path)] = $handler;
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     */
    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][$this->normalizePath($path)] = $handler;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $this->normalizePath($request->path());

        $handler = $this->routes[$method][$path] ?? null;
        if ($handler === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Not Found';
            return;
        }

        [$class, $action] = $handler;
        if (! class_exists($class)) {
            throw new RuntimeException("Controller class not found: {$class}");
        }

        $controller = new $class();
        if (! method_exists($controller, $action)) {
            throw new RuntimeException("Controller action not found: {$class}::{$action}");
        }

        $controller->{$action}($request);
    }

    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        return $path;
    }
}

