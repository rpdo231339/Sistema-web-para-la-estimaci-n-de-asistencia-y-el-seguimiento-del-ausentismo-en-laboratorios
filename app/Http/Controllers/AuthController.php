<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    // Limite de intentos fallidos (FF.1): 5 intentos en una ventana de 5 minutos
    private const MAX_INTENTOS = 5;
    private const VENTANA_SEGUNDOS = 300;

    public function loginForm()
    {
        if (Auth::check()) {
            return redirect()->route('panel');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'correo' => 'required|email|max:254',
            'password' => 'required|string',
        ]);

        // El correo se guarda normalizado (minusculas)
        $correo = Str::lower($request->correo);

        // El limite se aplica por correo + IP, exista o no la cuenta
        $clave = $correo . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            return back()
                ->withInput($request->only('correo'))
                ->with('error', __('auth.throttle', ['seconds' => RateLimiter::availableIn($clave)]));
        }

        // Solo entran cuentas activas; el mensaje de error es el mismo en todos los casos
        $credenciales = [
            'correo' => $correo,
            'password' => $request->password,
            'activo' => 1,
        ];

        if (Auth::attempt($credenciales)) {
            RateLimiter::clear($clave);
            $request->session()->regenerate();

            // Si cambia version_seguridad en la BD, esta sesion deja de ser valida
            $request->session()->put('version_seguridad', Auth::user()->version_seguridad);

            return redirect()->route('panel');
        }

        RateLimiter::hit($clave, self::VENTANA_SEGUNDOS);

        return back()
            ->withInput($request->only('correo'))
            ->with('error', __('auth.failed'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('acceso')->with('success', 'Sesión cerrada correctamente');
    }
}
