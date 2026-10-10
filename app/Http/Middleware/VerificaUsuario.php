<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificaUsuario
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sin sesion iniciada (o sesion vencida por inactividad)
        if (!Auth::check()) {
            return redirect()->route('acceso')
                ->with('error', 'Debes iniciar sesión para continuar');
        }

        $usuario = Auth::user();

        // Cuenta dada de baja, o sesion creada antes de un cambio de seguridad
        $versionSesion = (int) $request->session()->get('version_seguridad');

        if (!$usuario->activo || $versionSesion !== (int) $usuario->version_seguridad) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('acceso')
                ->with('error', 'Tu sesión ya no es válida. Inicia sesión de nuevo');
        }

        return $next($request);
    }
}
