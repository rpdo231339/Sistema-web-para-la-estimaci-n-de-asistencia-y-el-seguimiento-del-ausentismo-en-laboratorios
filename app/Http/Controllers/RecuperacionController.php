<?php

namespace App\Http\Controllers;

use App\Mail\RecuperarContrasenaCorreo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class RecuperacionController extends Controller
{
    public function solicitudForm()
    {
        return view('auth.olvide');
    }

    public function enviarEnlace(Request $request)
    {
        $request->validate([
            'correo' => 'required|email|max:254',
        ]);

        // Solo las cuentas activas pueden recuperar el acceso
        $credenciales = [
            'correo' => Str::lower($request->correo),
            'activo' => 1,
        ];

        try {
            // Genera el token (un solo uso) y envia el correo en español
            Password::sendResetLink($credenciales, function ($usuario, $token) {
                Mail::to($usuario->correo)->send(new RecuperarContrasenaCorreo($usuario, $token));
            });
        } catch (\Throwable $e) {
            // Si el correo falla, el usuario ve la misma respuesta y el fallo queda en el log
            Log::error('No se pudo enviar el correo de recuperación: ' . $e->getMessage());
        }

        // Respuesta general: no confirma si el correo existe, esta activo o fue limitado
        return back()->with('info', 'Si el correo está registrado y activo, recibirás un enlace para restablecer tu contraseña.');
    }

    public function restablecerForm(Request $request, string $token)
    {
        return view('auth.restablecer', [
            'token' => $token,
            'correo' => $request->query('correo'),
        ]);
    }

    public function restablecer(Request $request)
    {
        $request->merge(['correo' => Str::lower((string) $request->correo)]);

        $request->validate([
            'token' => 'required|string',
            'correo' => 'required|email|max:254',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $estado = Password::reset(
            [
                'correo' => $request->correo,
                'password' => $request->password,
                'password_confirmation' => $request->password_confirmation,
                'token' => $request->token,
                'activo' => 1,
            ],
            function ($usuario, $password) {
                // Cambia la contraseña y cierra las sesiones abiertas de la cuenta
                // Quien demuestra control del correo y elige contraseña tambien deja su cuenta activada
                $usuario->forceFill([
                    'password' => Hash::make($password),
                    'activado_en' => $usuario->activado_en ?? now(),
                    'version_seguridad' => $usuario->version_seguridad + 1,
                ])->save();
            }
        );

        if ($estado === Password::PASSWORD_RESET) {
            // El token se elimina al usarse: el enlace no sirve una segunda vez
            return redirect()->route('acceso')
                ->with('success', 'Contraseña restablecida correctamente. Ya puedes iniciar sesión');
        }

        // Token usado, vencido o inexistente: mismo mensaje en todos los casos
        return back()
            ->withInput($request->only('correo'))
            ->with('error', 'El enlace no es válido o ha caducado. Solicita uno nuevo');
    }
}
