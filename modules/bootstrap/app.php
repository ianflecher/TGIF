<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['portal' => \App\Http\Middleware\EnsurePortalAccess::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Framework database defaults reference Pdo\Mysql, introduced in PHP 8.4.
// On PHP 8.2/8.3 use the application's explicit configuration instead.
if (PHP_VERSION_ID < 80400) {
    $app->dontMergeFrameworkConfiguration();
}

return $app;
