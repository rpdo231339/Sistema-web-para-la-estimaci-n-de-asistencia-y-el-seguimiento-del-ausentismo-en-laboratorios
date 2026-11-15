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
    $middleware->alias([
        'usuario' => \App\Http\Middleware\VerificaUsuario::class,
        'rol' => \App\Http\Middleware\RolMiddleware::class,
    ]);

    $middleware->redirectGuestsTo(fn () => route('acceso'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sesion vencida por inactividad: el formulario enviado ya no tiene token valido (419).
        // En lugar de la pagina de error se regresa al acceso con un aviso claro.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 419 && !$request->expectsJson()) {
                return redirect()->route('acceso')
                    ->with('error', 'Tu sesión expiró por inactividad. Inicia sesión de nuevo');
            }
        });
    })->create();
