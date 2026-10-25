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
        \App\Http\Middleware\EnsureAccountIsActive::class,
        \App\Http\Middleware\EnsureTwoFactorVerified::class,
        \App\Http\Middleware\PreventAuthenticatedPageCaching::class,
    ]);
})
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every error line in laravel.log says who hit it and where, so a run
        // of 500s can be traced to one page, one account, one address.
        $exceptions->context(function () {
            try {
                return [
                    'user_id' => auth()->id(),
                    'method' => request()->method(),
                    'url' => request()->fullUrl(),
                    'ip' => request()->ip(),
                ];
            } catch (\Throwable) {
                return [];
            }
        });

        // A 403 is the server refusing a role, which is the control working —
        // but a run of them from one account is someone probing for HR
        // screens. Record it, then let Laravel render the usual error page.
        // Policy refusals arrive here too: they are mapped to a 403 first.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 403 && $request->user()) {
                app(\App\Services\ActivityLogger::class)->accessDenied(
                    $request->user(),
                    $request->method(),
                    $request->path(),
                    $request->route()?->getName(),
                );
            }

            return null;
        });
    })->create();
