<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Support\Facades\Auth;

class AuditoriaService
{
    // Registra un cambio relevante. Nunca se deben pasar contrasenas, tokens ni credenciales.
    public function registrar(
        string $entidad,
        ?int $registroId,
        string $accion,
        ?array $antes = null,
        ?array $despues = null,
        ?string $motivo = null
    ): Auditoria {
        return Auditoria::create([
            'usuario_id' => Auth::id(),
            'entidad' => $entidad,
            'registro_id' => $registroId,
            'accion' => $accion,
            'antes' => $antes,
            'despues' => $despues,
            'motivo' => $motivo,
        ]);
    }

    // Ultimo registro de una accion sobre un objeto (por ejemplo, la ultima reapertura de una asistencia)
    public function ultima(string $entidad, int $registroId, string $accion): ?Auditoria
    {
        return Auditoria::where('entidad', $entidad)
            ->where('registro_id', $registroId)
            ->where('accion', $accion)
            ->orderByDesc('id')
            ->first();
    }
}
