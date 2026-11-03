<?php

namespace App\Services;

use App\Models\Evaluacion;
use App\Models\Evidencia;
use App\Models\Procesamiento;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EvaluacionService
{
    public function __construct(private EvidenciaService $evidencias, private InferenciaService $inferencia)
    {
    }

    // Guarda la imagen de prueba, solicita su analisis y registra la referencia manual
    public function crear(UploadedFile $archivo, array $datos, Usuario $autor, string $clave): Evaluacion
    {
        $imagen = $this->evidencias->leerImagen($archivo);

        if (Procesamiento::where('clave_operacion', $clave)->exists()) {
            throw ValidationException::withMessages(['archivo' => 'Esta solicitud ya se registró. No la envíes dos veces']);
        }

        $disco = $this->evidencias->disco();
        $extension = config('evidencias.mimes')[$imagen['mime']];
        $ruta = $disco->putFileAs('evaluaciones', $archivo, 'eval-' . Str::uuid() . '.' . $extension);

        if (!$ruta) {
            throw new \RuntimeException('No se pudo guardar el archivo');
        }

        $evidencia = null;

        try {
            $evidencia = Evidencia::create([
                'pase_lista_id' => null,
                'version' => 1,
                'tipo' => 'imagen',
                'ruta' => $ruta,
                'nombre_original' => $this->nombreVisible($archivo, $extension),
                'mime' => $imagen['mime'],
                'bytes' => $archivo->getSize(),
                'sha256' => hash_file('sha256', $disco->path($ruta)),
                'ancho' => $imagen['ancho'],
                'alto' => $imagen['alto'],
                'capturada_en' => null,
                'cargado_por' => $autor->id,
            ]);

            return DB::transaction(function () use ($evidencia, $autor, $clave, $datos) {
                $procesamiento = $this->inferencia->solicitar($evidencia, $autor, $clave);

                return Evaluacion::create($datos + [
                    'procesamiento_id' => $procesamiento->id,
                    'registrado_por' => $autor->id,
                ]);
            });
        } catch (\Throwable $e) {
            // Si algo fallo, no quedan una imagen ni una evidencia huerfanas
            if ($evidencia && !Procesamiento::where('evidencia_id', $evidencia->id)->exists()) {
                Evidencia::whereKey($evidencia->id)->delete();
            }

            if (!$evidencia || !Evidencia::whereKey($evidencia->id)->exists()) {
                $disco->delete($ruta);
            }

            throw $e;
        }
    }

    /**
     * Metricas de exactitud del conteo automatico frente a la referencia manual.
     * Cada fila: ['ambiente','oclusion','conteo','referencia','tiempo_ms','omisiones','duplicaciones'].
     * Las diferencias se calculan por imagen; no se suman personas entre imagenes.
     */
    public static function resumen(array $filas): array
    {
        $vacio = [
            'n' => 0, 'mae' => null, 'sesgo' => null, 'exactas' => 0,
            'tiempo_medio_ms' => null, 'omisiones' => 0, 'duplicaciones' => 0,
        ];

        if (!$filas) {
            return $vacio;
        }

        $n = count($filas);
        $absoluto = 0;
        $conSigno = 0;
        $exactas = 0;
        $tiempo = 0;
        $omisiones = 0;
        $duplicaciones = 0;

        foreach ($filas as $f) {
            $d = (int) $f['conteo'] - (int) $f['referencia'];
            $absoluto += abs($d);
            $conSigno += $d;
            $exactas += $d === 0 ? 1 : 0;
            $tiempo += (int) $f['tiempo_ms'];
            $omisiones += (int) ($f['omisiones'] ?? 0);
            $duplicaciones += (int) ($f['duplicaciones'] ?? 0);
        }

        return [
            'n' => $n,
            'mae' => $absoluto / $n,
            'sesgo' => $conSigno / $n,
            'exactas' => $exactas,
            'tiempo_medio_ms' => $tiempo / $n,
            'omisiones' => $omisiones,
            'duplicaciones' => $duplicaciones,
        ];
    }

    // Agrupa las filas por una clave (ambiente u oclusion) y resume cada grupo
    public static function resumenPor(array $filas, string $clave): array
    {
        $grupos = [];

        foreach ($filas as $f) {
            $grupos[$f[$clave]][] = $f;
        }

        ksort($grupos);

        return array_map(fn ($g) => self::resumen($g), $grupos);
    }

    private function nombreVisible(UploadedFile $archivo, string $extension): string
    {
        $nombre = basename($archivo->getClientOriginalName());
        $nombre = preg_replace('/[\x00-\x1F\x7F]/u', '', $nombre);
        $nombre = Str::limit($nombre, 255, '');

        return $nombre !== '' ? $nombre : 'imagen.' . $extension;
    }
}
