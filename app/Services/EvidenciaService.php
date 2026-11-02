<?php

namespace App\Services;

use App\Models\Evidencia;
use App\Models\PaseLista;
use App\Models\Sesion;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EvidenciaService
{
    // Disco privado: storage/app/private, sin enlace publico
    public function disco()
    {
        return Storage::disk('local');
    }

    // Lee ancho, alto y tipo real; rechaza archivos danados o de un tipo no admitido
    public function leerImagen(UploadedFile $archivo): array
    {
        $info = @getimagesize($archivo->getRealPath());

        if ($info === false || empty($info[0]) || empty($info[1])) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo está dañado o no se pudo leer como imagen',
            ]);
        }

        $mime = $info['mime'] ?? '';

        if (!array_key_exists($mime, config('evidencias.mimes'))) {
            throw ValidationException::withMessages([
                'archivo' => 'Formato no admitido: usa JPG, JPEG o PNG',
            ]);
        }

        return [
            'ancho' => (int) $info[0],
            'alto' => (int) $info[1],
            'mime' => $mime,
        ];
    }

    // Guarda el archivo y crea una version nueva, que pasa a ser la vigente del pase
    public function guardarVersion(
        PaseLista $pase,
        UploadedFile $archivo,
        array $imagen,
        Carbon $capturadaUtc,
        Usuario $autor,
        ?string $observaciones
    ): Evidencia {
        $ruta = null;

        try {
            return DB::transaction(function () use ($pase, $archivo, $imagen, $capturadaUtc, $autor, $observaciones, &$ruta) {

                // Se bloquea el pase para que dos cargas simultaneas no tomen la misma version
                $bloqueado = PaseLista::whereKey($pase->id)->lockForUpdate()->first();

                $version = (int) Evidencia::where('pase_lista_id', $pase->id)->max('version') + 1;

                // Nombre interno: nunca se usa el nombre que escribio el usuario
                $extension = config('evidencias.mimes')[$imagen['mime']];
                $carpeta = 'evidencias/' . $bloqueado->sesion_id . '/' . $bloqueado->id;
                $nombreInterno = 'v' . $version . '-' . Str::uuid() . '.' . $extension;

                $ruta = $this->disco()->putFileAs($carpeta, $archivo, $nombreInterno);

                if (!$ruta) {
                    throw new \RuntimeException('No se pudo guardar el archivo');
                }

                $evidencia = Evidencia::create([
                    'pase_lista_id' => $bloqueado->id,
                    'version' => $version,
                    'tipo' => 'imagen',
                    'ruta' => $ruta,
                    'nombre_original' => $this->nombreVisible($archivo, $extension),
                    'mime' => $imagen['mime'],
                    'bytes' => $archivo->getSize(),
                    'sha256' => hash_file('sha256', $this->disco()->path($ruta)),
                    'ancho' => $imagen['ancho'],
                    'alto' => $imagen['alto'],
                    'capturada_en' => $capturadaUtc,
                    'cargado_por' => $autor->id,
                ]);

                // Una version nueva deja obsoleto el resultado anterior: el pase vuelve a "sin evidencia"
                $datos = ['estado' => 'sin_evidencia'];

                if ($observaciones) {
                    $zona = config('app.timezone_local', 'America/Mexico_City');
                    $nota = '[Evidencia v' . $version . ' · ' . now()->timezone($zona)->format('d/m/Y H:i') . '] ' . $observaciones;

                    $datos['observaciones'] = trim(($bloqueado->observaciones ? $bloqueado->observaciones . "\n" : '') . $nota);
                }

                $bloqueado->update($datos);

                return $evidencia;
            });
        } catch (\Throwable $e) {
            // Si algo fallo despues de guardar el archivo, no se deja huerfano
            if ($ruta) {
                $this->disco()->delete($ruta);
            }

            throw $e;
        }
    }

    // Quien puede ver los archivos de una sesion: el profesor titular y el encargado del espacio
    public function puedeVer(Usuario $usuario, Sesion $sesion): bool
    {
        if ($usuario->rol === 'profesor') {
            return (int) $sesion->profesor_id === (int) $usuario->id;
        }

        if ($usuario->rol === 'encargado') {
            return $usuario->espaciosAsignados()->where('espacios.id', $sesion->espacio_id)->exists();
        }

        return false;
    }

    // Nombre que se muestra al usuario: limpio y acotado
    private function nombreVisible(UploadedFile $archivo, string $extension): string
    {
        $nombre = basename($archivo->getClientOriginalName());
        $nombre = preg_replace('/[\x00-\x1F\x7F]/u', '', $nombre);
        $nombre = Str::limit($nombre, 255, '');

        return $nombre !== '' ? $nombre : 'imagen.' . $extension;
    }
}
