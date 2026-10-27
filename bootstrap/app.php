<?php

use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        // In production, requests arrive through Caddy and nginx on private Docker networks, and no
        // container publishes a port, so only those proxies can reach the app. Trusting them gives
        // Laravel the real client IP (which the PDF rate limiter relies on) and the https scheme.
        $middleware->trustProxies(at: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);
        $middleware->web(replace: [
            Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class => PreventRequestForgery::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
