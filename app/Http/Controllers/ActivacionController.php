<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ActivacionController extends Controller
{
    private const MENSAJE_ENLACE = 'El enlace de activación no es válido o ha caducado. Pide al administrador que te envíe uno nuevo';

    public function formulario(Request $request, string $token)
    {
        // Un enlace usado, vencido o de una cuenta ya activada ni siquiera muestra el formulario
        $broker = Password::broker('activaciones');
        $usuario = $broker->getUser([
            'correo' => Str::lower((string) $request->query('correo')),
            'activo' => 1,
        ]);

        if (!$usuario || $usuario->activado_en !== null || !$broker->tokenExists($usuario, $token)) {
            return redirect()->route('acceso')->with('error', self::MENSAJE_ENLACE);
        }

        return view('auth.activar', [
            'token' => $token,
            'correo' => $request->query('correo'),
        ]);
    }

    public function activar(Request $request)
    {
        $request->merge(['correo' => Str::lower((string) $request->correo)]);

        $request->validate([
            'token' => 'required|string',
            'correo' => 'required|email|max:254',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $estado = Password::broker('activaciones')->reset(
            [
                'correo' => $request->correo,
                'password' => $request->password,
                'password_confirmation' => $request->password_confirmation,
                'token' => $request->token,
                'activo' => 1,
            ],
            function ($usuario, $password) {
                // Solo una cuenta pendiente se activa; la contrasena la elige su dueno
                if ($usuario->activado_en !== null) {
                    return;
                }

                $usuario->forceFill([
                    'password' => Hash::make($password),
                    'activado_en' => now(),
                    'version_seguridad' => $usuario->version_seguridad + 1,
                ])->save();
            }
        );

        if ($estado === Password::PASSWORD_RESET) {
            // El token se elimina al usarse: el enlace no sirve una segunda vez
            return redirect()->route('acceso')
                ->with('success', 'Cuenta activada correctamente. Ya puedes iniciar sesión');
        }

        // Token usado, vencido o inexistente: mismo mensaje en todos los casos
        return back()
            ->withInput($request->only('correo'))
            ->with('error', self::MENSAJE_ENLACE);
    }
}
