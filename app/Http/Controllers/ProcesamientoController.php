<?php

namespace App\Http\Controllers;

use App\Models\Procesamiento;
use App\Services\EvidenciaService;
use Illuminate\Support\Facades\Auth;

class ProcesamientoController extends Controller
{
    // Imagen con las detecciones dibujadas; mismas reglas de acceso que la evidencia
    public function anotada(Procesamiento $procesamiento, EvidenciaService $servicio)
    {
        $pase = $procesamiento->evidencia ? $procesamiento->evidencia->pase : null;
        $sesion = $pase ? $pase->sesion : null;
        $ruta = data_get($procesamiento->datos_resultado, 'anotada');

        if (!$sesion || !$servicio->puedeVer(Auth::user(), $sesion) || !$ruta || !$servicio->disco()->exists($ruta)) {
            abort(404);
        }

        return $servicio->disco()->response($ruta, 'detecciones-' . $procesamiento->id . '.jpg', [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }
}
