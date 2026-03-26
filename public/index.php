<?php

declare(strict_types=1);

use App\Support\Request;

$router = require dirname(__DIR__) . '/bootstrap/app.php';

$request = new Request();
$router->dispatch($request);

