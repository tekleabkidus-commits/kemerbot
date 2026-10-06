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
    ->withMiddleware(function (Middleware $middleware): void {
        // Telegram posts updates cross-origin with its own auth (secret header).
        $middleware->validateCsrfTokens(except: [
            'telegram/webhook', 'integrations/conversions',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
