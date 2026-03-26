<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Request;

interface MiddlewareInterface
{
    public function handle(Request $request): bool;
}

