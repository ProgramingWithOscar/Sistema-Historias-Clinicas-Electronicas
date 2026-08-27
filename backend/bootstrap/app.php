<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Esta aplicación no tiene formulario de login web: al no haber ruta a
        // dónde redirigir, un invitado recibe 401 JSON en vez de un 500 por
        // `Route [login] not defined`.
        $middleware->redirectGuestsTo(fn () => null);

        // Modo SPA de Sanctum: las peticiones que vengan de los dominios
        // declarados en SANCTUM_STATEFUL_DOMAINS se autentican con la cookie de
        // sesión (HttpOnly + CSRF) en lugar de un token en localStorage, que
        // sería legible por cualquier script inyectado (XSS).
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
