<?php

namespace App\Http\Controllers;

use App\Models\Evidencia;
use App\Services\EvidenciaService;
use Illuminate\Support\Facades\Auth;

class EvidenciaController extends Controller
{
    // Entrega el archivo privado solo a quien tiene relacion con la sesion
    public function archivo(Evidencia $evidencia, EvidenciaService $servicio)
    {
        $sesion = $evidencia->pase ? $evidencia->pase->sesion : null;

        // Sin permiso o sin archivo la respuesta es la misma, para no revelar que la evidencia existe
        if (!$sesion || !$servicio->puedeVer(Auth::user(), $sesion) || !$servicio->disco()->exists($evidencia->ruta)) {
            abort(404);
        }

        return $servicio->disco()->response($evidencia->ruta, $evidencia->nombre_original, [
            'Content-Type' => $evidencia->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }
}
