<?php

namespace App\Services;

use App\Models\PaseLista;
use App\Models\Sesion;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class PaseListaService
{
    public const MOMENTOS = ['inicio', 'desarrollo', 'cierre'];

    public function zona(): string
    {
        return config('app.timezone_local', 'America/Mexico_City');
    }

    // Fecha 'Y-m-d' y hora 'H:i' en hora local -> instante en UTC
    public function aUtc(string $fecha, string $hora): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', $fecha . ' ' . substr($hora, 0, 5), $this->zona())->utc();
    }

    // Las tres horas deben ir en orden y dentro del horario de la sesion
    public function validarHoras(array $horas, string $inicioSesion, string $finSesion): void
    {
        $inicio = substr($inicioSesion, 0, 5);
        $fin = substr($finSesion, 0, 5);
        $errores = [];

        foreach (self::MOMENTOS as $momento) {
            $hora = $horas[$momento];

            if ($hora < $inicio || $hora > $fin) {
                $errores["momentos.$momento"] = "El momento de $momento debe estar dentro del horario de la sesión ($inicio–$fin)";
            }
        }

        if (!($horas['inicio'] < $horas['desarrollo'] && $horas['desarrollo'] < $horas['cierre'])) {
            $errores['momentos'] = 'Las horas deben ir en orden: inicio, desarrollo y cierre';
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    // Crea los tres pases si faltan y reprograma los que cambiaron de hora
    public function programar(Sesion $sesion, array $horas): void
    {
        $fecha = $sesion->fecha->format('Y-m-d');
        $existentes = $sesion->pases()->get()->keyBy('momento');

        foreach (self::MOMENTOS as $momento) {
            $nuevo = $this->aUtc($fecha, $horas[$momento]);
            $pase = $existentes->get($momento);

            if (!$pase) {
                PaseLista::create([
                    'sesion_id' => $sesion->id,
                    'momento' => $momento,
                    'programado_en' => $nuevo,
                    'version_programacion' => 1,
                    'estado' => 'sin_evidencia',
                ]);

                continue;
            }

            if ($pase->programado_en->eq($nuevo)) {
                continue;
            }

            // Un momento que ya tiene resultado no se reprograma
            if ($pase->estado !== 'sin_evidencia') {
                throw ValidationException::withMessages([
                    "momentos.$momento" => "El momento de $momento ya tiene evidencia procesada y su hora no se puede cambiar",
                ]);
            }

            // Subir la version permite descartar avisos de la programacion anterior
            $pase->update([
                'programado_en' => $nuevo,
                'version_programacion' => $pase->version_programacion + 1,
            ]);
        }
    }

    // Si la sesion cambia de fecha, los tres momentos conservan su hora local en la fecha nueva
    public function moverFecha(Sesion $sesion, string $nuevaFecha): void
    {
        foreach ($sesion->pases()->get() as $pase) {
            $hora = $pase->programado_en->copy()->timezone($this->zona())->format('H:i');

            $pase->update([
                'programado_en' => $this->aUtc($nuevaFecha, $hora),
                'version_programacion' => $pase->version_programacion + 1,
            ]);
        }
    }
}
