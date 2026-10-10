<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RolMiddleware
{
    // Uso en rutas: ->middleware(['usuario', 'rol:administrador,encargado'])
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!in_array(Auth::user()->rol, $roles, true)) {
            return redirect()->route('panel')
                ->with('error', 'No tienes permiso para acceder a esa sección');
        }

        return $next($request);
    }
}
