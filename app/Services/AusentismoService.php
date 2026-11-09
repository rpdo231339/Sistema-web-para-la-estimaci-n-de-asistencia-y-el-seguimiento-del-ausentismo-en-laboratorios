<?php

namespace App\Services;

use App\Models\Sesion;
use Illuminate\Validation\ValidationException;

class AusentismoService
{
    public const MOMENTOS = ['inicio', 'desarrollo', 'cierre'];

    /**
     * Formula de FN.12 con aritmetica entera (sin errores de coma flotante).
     *   media      = (c1 + c2 + c3) / 3
     *   ausentismo = (inscritos - media) / inscritos * 100
     * Redondeo "half-up" a 4 decimales; en pantalla se muestran 2.
     * Devuelve cadenas decimales listas para guardar.
     */
// Calcula la suma, media y porcentaje de ausentismo de tres conteos.
public static function formula(int $inscritos, array $conteos): array
{
    // Verifica que exista al menos un inscrito.
    if ($inscritos <= 0) {
        throw new \InvalidArgumentException('Los inscritos deben ser mayores que cero');
    }

    // Comprueba que se proporcionen exactamente tres conteos.
    if (count($conteos) !== 3) {
        throw new \InvalidArgumentException('Se requieren exactamente tres conteos');
    }

    // Valida que cada conteo sea un entero entre cero y los inscritos.
    foreach ($conteos as $c) {
        if (!is_int($c) || $c < 0 || $c > $inscritos) {
            throw new \InvalidArgumentException('Cada conteo debe ser un entero entre 0 y los inscritos');
        }
    }

    // Calcula la suma total de los tres conteos.
    $suma = array_sum($conteos);

    // Calcula el total máximo posible de los tres conteos.
    $den = 3 * $inscritos;

    // Calcula la media con cuatro decimales.
    $media = intdiv(2 * $suma * 10000 + 3, 6);

    // Calcula el porcentaje de ausentismo con cuatro decimales.
    $ausentismo = intdiv(2 * ($den - $suma) * 1000000 + $den, 2 * $den);

    // Devuelve los resultados con formato de cuatro decimales.
    return [
        'suma' => $suma,
        'media' => sprintf('%d.%04d', intdiv($media, 10000), $media % 10000),
        'ausentismo' => sprintf('%d.%04d', intdiv($ausentismo, 10000), $ausentismo % 10000),
    ];
}

    /**
     * Reune los tres conteos vigentes y revisados de la sesion.
     * Si falta algo, lanza un error con cada motivo; nunca se calcula con datos incompletos.
     */
    public function reunir(Sesion $sesion): array
    {
        $errores = [];

        if ($sesion->estado === 'cancelada') {
            $errores[] = 'La sesión está cancelada y no admite estimaciones';
        }

        $sesion->load('pases.evidenciaVigente.procesamientoActual.revisionActual');

        $porMomento = $sesion->pases->keyBy('momento');
        $conteos = [];
        $origen = [];

        foreach (self::MOMENTOS as $momento) {
            $etiqueta = ucfirst($momento);
            $pase = $porMomento->get($momento);

            if (!$pase) {
                $errores[] = "$etiqueta: el momento no está programado";
                continue;
            }

            $proc = optional($pase->evidenciaVigente)->procesamientoActual;
            $revision = ($proc && $proc->estado === 'procesado') ? $proc->revisionActual : null;

            if (!$pase->evidenciaVigente || !$proc || $proc->estado !== 'procesado') {
                $errores[] = "$etiqueta: no tiene un resultado procesado de la imagen vigente";
                continue;
            }

            if (!$revision || $revision->decision !== 'validado' || $revision->conteo_revisado === null || $pase->estado !== 'validado') {
                $errores[] = "$etiqueta: el conteo todavía no está validado por el profesor";
                continue;
            }

            $valor = (int) $revision->conteo_revisado;

            if ($valor < 0 || $valor > (int) $sesion->inscritos) {
                $errores[] = "$etiqueta: el conteo validado ($valor) está fuera del rango permitido, de 0 a {$sesion->inscritos}";
                continue;
            }

            $conteos[$momento] = $valor;
            $origen[] = [
                'momento' => $momento,
                'pase_id' => $pase->id,
                'procesamiento_id' => $proc->id,
                'revision_id' => $revision->id,
                'conteo_automatico' => $proc->conteo,
                'conteo_revisado' => $valor,
            ];
        }

        if ($errores) {
            throw ValidationException::withMessages(['estimacion' => $errores]);
        }

        return [
            'inscritos' => (int) $sesion->inscritos,
            'conteos' => $conteos,
            'origen' => $origen,
        ];
    }

    // Revisiones validadas que hoy sostienen cada momento (sirve para saber si un calculo quedo desactualizado)
    public function revisionesVigentes(Sesion $sesion): array
    {
        $sesion->loadMissing('pases.evidenciaVigente.procesamientoActual.revisionActual');

        $ids = [];

        foreach ($sesion->pases as $pase) {
            $proc = optional($pase->evidenciaVigente)->procesamientoActual;
            $rev = ($proc && $proc->estado === 'procesado') ? $proc->revisionActual : null;

            if ($rev && $rev->decision === 'validado' && $pase->estado === 'validado') {
                $ids[$pase->momento] = $rev->id;
            }
        }

        return $ids;
    }
}
