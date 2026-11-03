<?php

namespace App\Http\Controllers;

use App\Models\EstimacionPresencia;
use App\Models\Sesion;
use App\Services\AusentismoService;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EstimacionController extends Controller
{
    // Calcula y guarda la estimacion de presencia y ausentismo de la sesion
    public function calcular(Sesion $sesion, AusentismoService $servicio, AuditoriaService $auditoria)
    {
        if ((int) $sesion->profesor_id !== (int) Auth::id()) {
            return redirect()->route('sesiones.index')->with('error', 'No tienes permiso para gestionar esa sesión');
        }

        $resultado = DB::transaction(function () use ($sesion, $servicio, $auditoria) {

            // Se bloquea la sesion para que dos clics simultaneos no dupliquen el calculo
            $bloqueada = Sesion::whereKey($sesion->id)->lockForUpdate()->first();

            // Lanza ValidationException con cada motivo si faltan datos o estan fuera de rango
            $datos = $servicio->reunir($bloqueada);

            $conteos = $datos['conteos'];
            $calculo = AusentismoService::formula($datos['inscritos'], [
                $conteos['inicio'], $conteos['desarrollo'], $conteos['cierre'],
            ]);

            // Si nada cambio desde el ultimo calculo, no se crea una fila repetida
            $ultima = EstimacionPresencia::where('sesion_id', $bloqueada->id)->orderByDesc('id')->first();
            $nuevasRevisiones = collect($datos['origen'])->pluck('revision_id', 'momento')->all();

            if ($ultima && $ultima->revisionesOrigen() == $nuevasRevisiones && (int) $ultima->inscritos === $datos['inscritos']) {
                return ['repetida' => true];
            }

            $estimacion = EstimacionPresencia::create([
                'sesion_id' => $bloqueada->id,
                'calculado_por' => Auth::id(),
                'inscritos' => $datos['inscritos'],
                'conteo_inicio' => $conteos['inicio'],
                'conteo_desarrollo' => $conteos['desarrollo'],
                'conteo_cierre' => $conteos['cierre'],
                'media' => $calculo['media'],
                'ausentismo' => $calculo['ausentismo'],
                'origen' => $datos['origen'],
            ]);

            $auditoria->registrar(
                'estimaciones_presencia',
                $estimacion->id,
                'calculo',
                $ultima ? ['media' => $ultima->media, 'ausentismo' => $ultima->ausentismo] : null,
                ['media' => $calculo['media'], 'ausentismo' => $calculo['ausentismo'], 'conteos' => $conteos, 'inscritos' => $datos['inscritos']],
                null
            );

            return ['repetida' => false];
        });

        if ($resultado['repetida']) {
            return back()->with('success', 'La estimación vigente ya corresponde a los conteos validados actuales');
        }

        return back()->with('success', 'Estimación de presencia y ausentismo calculada correctamente');
    }
}
