<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use LaravelMpesa\Http\Controllers\Callbacks\StkCallbackController;

$callbacks = config('mpesa.callbacks', []);
$middleware = is_array($callbacks) ? ($callbacks['middleware'] ?? []) : [];
$routes = is_array($callbacks) ? ($callbacks['routes'] ?? []) : [];
$controllers = is_array($callbacks) ? ($callbacks['controllers'] ?? []) : [];
$pathPrefix = is_array($callbacks) ? ($callbacks['path_prefix'] ?? '/signal/ingress') : '/signal/ingress';

$resolvePath = static function (string $key, string $defaultSlug) use ($routes, $pathPrefix): string {
    $configured = is_array($routes) ? ($routes[$key] ?? null) : null;

    if (is_string($configured) && trim($configured) !== '') {
        return '/'.ltrim(trim($configured), '/');
    }

    $normalizedPrefix = trim((string) $pathPrefix);

    if ($normalizedPrefix === '') {
        return '/'.ltrim($defaultSlug, '/');
    }

    return '/'.trim($normalizedPrefix, '/').'/'.ltrim($defaultSlug, '/');
};

$resolveController = static function (string $key, string $defaultController) use ($controllers): string {
    $configured = is_array($controllers) ? ($controllers[$key] ?? null) : null;

    return is_string($configured) && trim($configured) !== '' ? $configured : $defaultController;
};

Route::middleware(is_array($middleware) ? $middleware : [])
    ->group(static function () use ($resolvePath, $resolveController): void {
        Route::post(
            $resolvePath('stk', 'pulse'),
            $resolveController('stk', StkCallbackController::class),
        )->name('mpesa.callbacks.stk');
    });