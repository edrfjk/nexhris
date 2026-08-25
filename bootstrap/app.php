<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        '2fa' => \App\Http\Middleware\EnsureTwoFactorVerified::class,
    ]);

    // Every authenticated web request passes the two-factor gate, so a
    // half-verified session cannot reach any page by typing its URL.
    $middleware->web(append: [
        \App\Http\Middleware\EnsureTwoFactorVerified::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
