<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Router
{
    /**
     * @var array<string, array<string, array{
     *   handler: array{0: class-string, 1: string},
     *   middleware: array<int, class-string>
     * }>>
     */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array<int, class-string> $middleware
     */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->routes['GET'][$this->normalizePath($path)] = [
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array<int, class-string> $middleware
     */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->routes['POST'][$this->normalizePath($path)] = [
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = $this->normalizePath($request->path());

        $route = $this->routes[$method][$path] ?? null;
        if ($route === null) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Not Found';
            return;
        }

        $middlewares = $route['middleware'] ?? [];
        foreach ($middlewares as $middlewareClass) {
            if (! class_exists($middlewareClass)) {
                throw new RuntimeException("Middleware class not found: {$middlewareClass}");
            }
            $middleware = new $middlewareClass();
            if (! method_exists($middleware, 'handle')) {
                throw new RuntimeException("Middleware handle method not found: {$middlewareClass}");
            }
            $shouldContinue = (bool) $middleware->handle($request);
            if (! $shouldContinue) {
                return;
            }
        }

        $handler = $route['handler'];
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
