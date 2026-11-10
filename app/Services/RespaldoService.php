<?php

namespace App\Services;

use App\Models\Respaldo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class RespaldoService
{
    // ------------------------------------------------------------------ crear una copia

    // Genera el archivo de copia (base de datos + archivos) y deja el registro completado o con error
    public function crear(int $respaldoId): void
    {
        if (!$this->tomar($respaldoId)) {
            return;
        }

        $respaldo = Respaldo::findOrFail($respaldoId);
        $temporal = null;

        try {
            $this->generar($respaldo, $temporal);
        } catch (\Throwable $e) {
            $this->marcarError($respaldo, 'No se pudo generar la copia: ' . $e->getMessage());
            report($e);
        } finally {
            if ($temporal && is_file($temporal)) {
                @unlink($temporal);
            }
        }
    }

    private function generar(Respaldo $respaldo, ?string &$temporal): void
    {
        $disco = Storage::disk('local');
        $destino = config('respaldos.carpeta_destino');
        $disco->makeDirectory($destino . '/tmp');

        $nombre = 'respaldo_' . now()->format('Ymd_His') . '_' . Str::lower(Str::random(8)) . '.zip';
        $ruta = $destino . '/' . $nombre;
        $rutaZip = $disco->path($ruta);
        $temporal = $disco->path($destino . '/tmp/dump_' . $respaldo->id . '.ndjson');

        // Base de datos: una lectura consistente de todas las tablas
        $tablas = $this->tablasIncluidas();
        $conteos = $this->volcarBaseDatos($tablas, $temporal);

        // Archivos
        $archivos = $this->listarArchivos();

        $zip = new ZipArchive();

        if ($zip->open($rutaZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de copia');
        }

        $zip->addFile($temporal, 'base_datos.ndjson');

        foreach ($archivos as $relativa => $info) {
            $zip->addFile($disco->path($relativa), 'archivos/' . $relativa);
        }

        $manifiesto = [
            'formato' => config('respaldos.formato'),
            'generado_en' => now()->utc()->toIso8601String(),
            'migraciones' => $this->migracionesActuales(),
            'tablas' => $conteos,
            'archivos' => $archivos,
        ];

        $zip->addFromString('manifiesto.json', json_encode($manifiesto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        if (!$zip->close()) {
            throw new RuntimeException('No se pudo terminar el archivo de copia');
        }

        $respaldo->update([
            'estado' => 'completado',
            'ruta' => $ruta,
            'bytes' => filesize($rutaZip),
            'sha256' => hash_file('sha256', $rutaZip),
            'manifiesto' => [
                'formato' => $manifiesto['formato'],
                'tablas' => $conteos,
                'archivos' => count($archivos),
                'migraciones' => count($manifiesto['migraciones']),
            ],
            'detalle' => 'Copia generada: ' . array_sum($conteos) . ' filas en ' . count($conteos) . ' tablas y ' . count($archivos) . ' archivos.',
            'terminado_en' => now(),
        ]);
    }

    // ------------------------------------------------------------------ verificar integridad

    // Devuelve la lista de problemas encontrados; vacia significa que la copia es integra y compatible
    public function verificar(Respaldo $respaldo): array
    {
        $problemas = [];

        if ($respaldo->operacion !== 'respaldo' || $respaldo->estado !== 'completado' || !$respaldo->ruta) {
            return ['El registro no es una copia completada'];
        }

        $ruta = $this->rutaSegura($respaldo->ruta);

        if (!$ruta || !is_file($ruta)) {
            return ['El archivo de la copia no existe en el almacenamiento protegido'];
        }

        if (filesize($ruta) !== (int) $respaldo->bytes) {
            $problemas[] = 'El tamaño del archivo no coincide con el registrado';
        }

        if (!hash_equals((string) $respaldo->sha256, hash_file('sha256', $ruta))) {
            return array_merge($problemas, ['La huella SHA-256 no coincide: el archivo fue alterado o está dañado']);
        }

        $zip = new ZipArchive();

        if ($zip->open($ruta, ZipArchive::RDONLY) !== true) {
            return array_merge($problemas, ['El archivo de la copia no se puede abrir']);
        }

        try {
            $manifiesto = json_decode((string) $zip->getFromName('manifiesto.json'), true);

            if (!is_array($manifiesto) || ($manifiesto['formato'] ?? null) !== config('respaldos.formato')) {
                return array_merge($problemas, ['El manifiesto de la copia falta o es de un formato no compatible']);
            }

            // Misma version del esquema: no se restaura una copia de otra estructura
            $actuales = $this->migracionesActuales();
            $copia = $manifiesto['migraciones'] ?? [];

            if ($actuales !== $copia) {
                $problemas[] = 'La copia pertenece a otra versión de la estructura de la base de datos (migraciones distintas)';
            }

            // Cada archivo debe existir y conservar su huella
            foreach (($manifiesto['archivos'] ?? []) as $relativa => $info) {
                if (!$this->rutaRelativaValida($relativa, true)) {
                    $problemas[] = 'El manifiesto contiene una ruta no permitida';
                    continue;
                }

                $flujo = $zip->getStream('archivos/' . $relativa);

                if (!$flujo) {
                    $problemas[] = 'Falta un archivo dentro de la copia: ' . $relativa;
                    continue;
                }

                $ctx = hash_init('sha256');
                hash_update_stream($ctx, $flujo);
                fclose($flujo);

                if (!hash_equals($info['sha256'] ?? '', hash_final($ctx))) {
                    $problemas[] = 'Un archivo de la copia está dañado: ' . $relativa;
                }
            }

            // La base de datos: mismas tablas y mismo numero de filas que el manifiesto
            $conteoDump = [];
            $creadas = [];
            $flujo = $zip->getStream('base_datos.ndjson');

            if (!$flujo) {
                return array_merge($problemas, ['Falta la base de datos dentro de la copia']);
            }

            while (($linea = fgets($flujo)) !== false) {
                $bloque = json_decode($linea, true);

                if (!is_array($bloque) || !isset($bloque['t'])) {
                    $problemas[] = 'La base de datos de la copia tiene un bloque ilegible';
                    break;
                }

                if (isset($bloque['create'])) {
                    $creadas[$bloque['t']] = true;
                    $conteoDump[$bloque['t']] = $conteoDump[$bloque['t']] ?? 0;
                } elseif (isset($bloque['filas'])) {
                    $conteoDump[$bloque['t']] = ($conteoDump[$bloque['t']] ?? 0) + count($bloque['filas']);
                }
            }

            fclose($flujo);

            $esperado = $manifiesto['tablas'] ?? [];
            ksort($esperado);
            ksort($conteoDump);

            if ($esperado !== $conteoDump || count($creadas) !== count($esperado)) {
                $problemas[] = 'Las tablas o el número de filas de la copia no coinciden con su manifiesto';
            }
        } finally {
            $zip->close();
        }

        return $problemas;
    }

    // ------------------------------------------------------------------ restaurar

    // Restaura la copia de origen. Antes se genero una copia preventiva del estado actual (la crea el job).
    public function restaurar(int $restauracionId): void
    {
        if (!$this->tomar($restauracionId)) {
            return;
        }

        $restauracion = Respaldo::findOrFail($restauracionId);
        $origen = Respaldo::find($restauracion->respaldo_origen_id);
        $preventivo = Respaldo::find($restauracion->respaldo_preventivo_id);

        try {
            if (!$origen || !$preventivo) {
                throw new RuntimeException('Falta la copia de origen o la copia preventiva');
            }

            // 1) Copia preventiva del estado actual: si no se puede generar, no se toca nada
            $this->crear($preventivo->id);
            $preventivo->refresh();

            if ($preventivo->estado !== 'completado') {
                throw new RuntimeException('No se pudo generar la copia preventiva, por eso no se restauró nada. ' . $preventivo->detalle);
            }

            // 2) Integridad de la copia elegida, antes de tocar el sistema
            $problemas = $this->verificar($origen);

            if ($problemas) {
                throw new RuntimeException('La copia elegida no pasó la verificación, por eso no se restauró nada: ' . implode('; ', $problemas));
            }
        } catch (\Throwable $e) {
            $this->marcarError($restauracion, $e->getMessage());

            return;
        }

        // 3) Se aplica; si falla, se intenta volver al estado previo con la copia preventiva
        try {
            $this->aplicar($origen);

            $huerfanos = DB::table('respaldos')->whereNotIn('administrador_id', DB::table('usuarios')->select('id'))->count();

            $restauracion->update([
                'estado' => 'completado',
                'detalle' => 'Restauración aplicada desde la copia #' . $origen->id . '. Copia preventiva: #' . $preventivo->id . '.'
                    . ($huerfanos ? ' Hay ' . $huerfanos . ' registro(s) del historial cuyo administrador ya no existe en los datos restaurados.' : ''),
                'terminado_en' => now(),
            ]);

            // La bitacora restaurada es la de la copia: se deja constancia de la restauracion en ella
            try {
                app(AuditoriaService::class)->registrar('respaldo', $restauracion->id, 'restauracion_aplicada', null, [
                    'copia_origen' => $origen->id,
                    'copia_preventiva' => $preventivo->id,
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        } catch (\Throwable $e) {
            report($e);
            $detalle = 'La restauración falló: ' . $e->getMessage();

            try {
                $this->aplicar($preventivo);
                $detalle .= ' Se volvió al estado anterior con la copia preventiva #' . $preventivo->id . '.';
            } catch (\Throwable $e2) {
                report($e2);
                $detalle .= ' Además falló el regreso automático (' . $e2->getMessage() . '). Restaura manualmente la copia preventiva #' . $preventivo->id . '.';
            }

            $this->marcarError($restauracion, $detalle);
        }
    }

    // Reemplaza archivos y base de datos por el contenido de una copia ya verificada
    private function aplicar(Respaldo $copia): void
    {
        $disco = Storage::disk('local');
        $ruta = $this->rutaSegura($copia->ruta);

        if (!$ruta || !is_file($ruta)) {
            throw new RuntimeException('No existe el archivo de la copia #' . $copia->id);
        }

        $zip = new ZipArchive();

        if ($zip->open($ruta, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('No se pudo abrir la copia #' . $copia->id);
        }

        $base = $disco->path(config('respaldos.carpeta_destino') . '/tmp');
        $etapa = $base . DIRECTORY_SEPARATOR . 'etapa_' . $copia->id . '_' . Str::random(6);
        $previo = $base . DIRECTORY_SEPARATOR . 'previo_' . $copia->id . '_' . Str::random(6);
        $raiz = rtrim($disco->path(''), '/\\');
        $movidas = [];

        try {
            $manifiesto = json_decode((string) $zip->getFromName('manifiesto.json'), true);

            // a) Archivos de la copia a una carpeta de etapa
            @mkdir($etapa, 0775, true);
            @mkdir($previo, 0775, true);

            foreach (array_keys($manifiesto['archivos'] ?? []) as $relativa) {
                if (!$this->rutaRelativaValida($relativa, true)) {
                    throw new RuntimeException('Ruta no permitida en la copia');
                }

                $destino = $etapa . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativa);
                @mkdir(dirname($destino), 0775, true);

                $entrada = $zip->getStream('archivos/' . $relativa);
                $salida = fopen($destino, 'wb');

                if (!$entrada || !$salida || stream_copy_to_stream($entrada, $salida) === false) {
                    throw new RuntimeException('No se pudo extraer ' . $relativa);
                }

                fclose($entrada);
                fclose($salida);
            }

            // b) Se intercambian las carpetas de archivos: la actual se guarda aparte
            foreach (config('respaldos.carpetas') as $carpeta) {
                $actual = $raiz . DIRECTORY_SEPARATOR . $carpeta;
                $nueva = $etapa . DIRECTORY_SEPARATOR . $carpeta;

                if (is_dir($actual) && !rename($actual, $previo . DIRECTORY_SEPARATOR . $carpeta)) {
                    throw new RuntimeException('No se pudo apartar la carpeta ' . $carpeta);
                }

                $movidas[] = $carpeta;

                if (is_dir($nueva)) {
                    if (!rename($nueva, $actual)) {
                        throw new RuntimeException('No se pudo colocar la carpeta ' . $carpeta);
                    }
                } else {
                    @mkdir($actual, 0775, true);
                }
            }

            // c) Base de datos
            $this->cargarBaseDatos($zip);
        } catch (\Throwable $e) {
            // Las carpetas de archivos vuelven a su lugar
            foreach ($movidas as $carpeta) {
                $actual = $raiz . DIRECTORY_SEPARATOR . $carpeta;
                $guardada = $previo . DIRECTORY_SEPARATOR . $carpeta;

                if (is_dir($guardada)) {
                    $this->borrarCarpeta($actual);
                    @rename($guardada, $actual);
                }
            }

            throw $e;
        } finally {
            $zip->close();
            $this->borrarCarpeta($etapa);
        }

        // Todo salio bien: se descartan las carpetas anteriores (estan en la copia preventiva)
        $this->borrarCarpeta($previo);

        // Las sesiones abiertas dejan de ser validas: todos deben iniciar sesion de nuevo
        DB::table('sessions')->truncate();
    }

    private function cargarBaseDatos(ZipArchive $zip): void
    {
        $pdo = DB::connection()->getPdo();
        $flujo = $zip->getStream('base_datos.ndjson');

        if (!$flujo) {
            throw new RuntimeException('Falta la base de datos en la copia');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

        try {
            while (($linea = fgets($flujo)) !== false) {
                $bloque = json_decode($linea, true, 512, JSON_THROW_ON_ERROR);
                $tabla = $bloque['t'];

                if (!preg_match('/^[A-Za-z0-9_]+$/', $tabla) || in_array($tabla, config('respaldos.tablas_excluidas'), true)) {
                    throw new RuntimeException('Tabla no permitida en la copia');
                }

                if (isset($bloque['create'])) {
                    $pdo->exec('DROP TABLE IF EXISTS `' . $tabla . '`');
                    $pdo->exec($bloque['create']);
                } elseif (!empty($bloque['filas'])) {
                    DB::table($tabla)->insert($bloque['filas']);
                }
            }
        } finally {
            fclose($flujo);
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    // ------------------------------------------------------------------ auxiliares

    // Escribe el volcado como lineas JSON y devuelve las filas por tabla
    private function volcarBaseDatos(array $tablas, string $archivo): array
    {
        $pdo = DB::connection()->getPdo();
        $conteos = [];
        $porBloque = config('respaldos.filas_por_bloque');

        $salida = fopen($archivo, 'wb');

        if (!$salida) {
            throw new RuntimeException('No se pudo escribir el volcado temporal');
        }

        // Lectura consistente: todas las tablas se leen en el mismo instante
        $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');

        try {
            foreach ($tablas as $tabla) {
                $create = DB::selectOne('SHOW CREATE TABLE `' . $tabla . '`');
                $sql = ((array) $create)['Create Table'];

                fwrite($salida, json_encode(['t' => $tabla, 'create' => $sql], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");

                $total = 0;
                $bloque = [];

                foreach (DB::table($tabla)->cursor() as $fila) {
                    $bloque[] = (array) $fila;
                    $total++;

                    if (count($bloque) >= $porBloque) {
                        fwrite($salida, json_encode(['t' => $tabla, 'filas' => $bloque], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
                        $bloque = [];
                    }
                }

                if ($bloque) {
                    fwrite($salida, json_encode(['t' => $tabla, 'filas' => $bloque], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
                }

                $conteos[$tabla] = $total;
            }
        } finally {
            $pdo->exec('COMMIT');
            fclose($salida);
        }

        return $conteos;
    }

    private function tablasIncluidas(): array
    {
        $filas = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        $tablas = [];

        foreach ($filas as $fila) {
            $nombre = array_values((array) $fila)[0];

            if (!in_array($nombre, config('respaldos.tablas_excluidas'), true)) {
                $tablas[] = $nombre;
            }
        }

        sort($tablas);

        return $tablas;
    }

    // Archivos de las carpetas incluidas: ruta relativa => tamano y huella
    private function listarArchivos(): array
    {
        $disco = Storage::disk('local');
        $lista = [];

        foreach (config('respaldos.carpetas') as $carpeta) {
            foreach ($disco->allFiles($carpeta) as $relativa) {
                $lista[$relativa] = [
                    'bytes' => $disco->size($relativa),
                    'sha256' => hash_file('sha256', $disco->path($relativa)),
                ];
            }
        }

        ksort($lista);

        return $lista;
    }

    private function migracionesActuales(): array
    {
        $lista = DB::table('migrations')->pluck('migration')->all();
        sort($lista);

        return array_values($lista);
    }

    // Solo rutas relativas dentro de las carpetas incluidas, sin saltos de directorio
    private function rutaRelativaValida(string $ruta, bool $dentroDeCarpetas = false): bool
    {
        if ($ruta === '' || str_contains($ruta, '..') || str_contains($ruta, '\\') || str_starts_with($ruta, '/') || preg_match('/^[A-Za-z]:/', $ruta)) {
            return false;
        }

        if ($dentroDeCarpetas) {
            return in_array(explode('/', $ruta)[0], config('respaldos.carpetas'), true);
        }

        return true;
    }

    // Ruta absoluta de una copia, solo si esta dentro de la carpeta protegida de copias
    public function rutaSegura(?string $ruta): ?string
    {
        $destino = config('respaldos.carpeta_destino');

        if (!$ruta || !str_starts_with($ruta, $destino . '/') || !$this->rutaRelativaValida($ruta)) {
            return null;
        }

        return Storage::disk('local')->path($ruta);
    }

    // Toma el registro de forma atomica: solo un proceso puede ejecutarlo
    private function tomar(int $id): bool
    {
        return Respaldo::whereKey($id)->where('estado', 'pendiente')->update(['estado' => 'en_proceso', 'updated_at' => now()]) === 1;
    }

    public function marcarError(Respaldo $respaldo, string $detalle): void
    {
        $respaldo->update([
            'estado' => 'error',
            'detalle' => Str::limit($detalle, 2000),
            'terminado_en' => now(),
        ]);
    }

    private function borrarCarpeta(string $carpeta): void
    {
        if (!is_dir($carpeta)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($carpeta, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($carpeta);
    }
}
