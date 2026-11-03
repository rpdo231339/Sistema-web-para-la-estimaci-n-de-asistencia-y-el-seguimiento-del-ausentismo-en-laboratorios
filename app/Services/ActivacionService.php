<?php

namespace App\Services;

use App\Mail\ActivarCuentaCorreo;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class ActivacionService
{
    public const ENVIADO = 'enviado';
    public const ESPERA = 'espera';
    public const ERROR = 'error';

    // Una cuenta esta pendiente mientras su dueno no haya definido su propia contrasena
    public function pendiente(Usuario $usuario): bool
    {
        return $usuario->activado_en === null;
    }

    // Genera un token de un solo uso y envia el enlace. Nunca se envia una contrasena por correo.
    public function enviar(Usuario $usuario): string
    {
        if (!$this->pendiente($usuario) || !$usuario->activo) {
            return self::ERROR;
        }

        try {
            $estado = Password::broker('activaciones')->sendResetLink(
                ['correo' => $usuario->correo],
                function ($destinatario, $token) {
                    Mail::to($destinatario->correo)->send(new ActivarCuentaCorreo($destinatario, $token));
                }
            );
        } catch (\Throwable $e) {
            // El token no se registra en el log; solo el motivo del fallo
            Log::error('No se pudo enviar el correo de activación: ' . $e->getMessage());

            return self::ERROR;
        }

        return $estado === Password::RESET_THROTTLED ? self::ESPERA : self::ENVIADO;
    }

    // Elimina los enlaces de activacion pendientes de un correo
    public function descartar(string $correo): void
    {
        \Illuminate\Support\Facades\DB::table('activacion_tokens')->where('email', $correo)->delete();
    }
}
