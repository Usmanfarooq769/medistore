<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware) {
         $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'partner.api' => \App\Http\Middleware\PartnerApiAuthentication::class,
            'partner.scope' => \App\Http\Middleware\PartnerApiScope::class,
            'customer.portal' => \App\Http\Middleware\CustomerPortalAuthentication::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());
    })->create();
