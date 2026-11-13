<?php

namespace App\Services;

use App\Jobs\ProcesarConteo;
use App\Models\ConfiguracionModelo;
use App\Models\Evidencia;
use App\Models\PaseLista;
use App\Models\Procesamiento;
use App\Models\Usuario;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

class InferenciaService
{
    public function __construct(private EvidenciaService $evidencias)
    {
    }

    // Registra la solicitud y la publica en la cola despues de confirmar la transaccion
    public function solicitar(Evidencia $evidencia, Usuario $usuario, string $clave): Procesamiento
    {
        // Una evidencia sin pase de lista es una imagen de evaluacion (FN.11): se analiza igual,
        // pero no actualiza ningun pase

        $configuracion = ConfiguracionModelo::where('activo', 1)->orderByDesc('id')->first();

        if (!$configuracion) {
            $this->rechazar('No hay una configuración del modelo registrada. Ejecuta: php artisan modelo:registrar');
        }

        $this->expirarAtascados($evidencia);

        return DB::transaction(function () use ($evidencia, $usuario, $clave, $configuracion) {

            if ($evidencia->pase_lista_id !== null) {
                // Se bloquea el pase para que dos clics simultaneos no lancen dos analisis
                $pase = PaseLista::whereKey($evidencia->pase_lista_id)->lockForUpdate()->first();

                if ($this->idVigente($pase) !== $evidencia->id) {
                    $this->rechazar('Solo se puede analizar la versión vigente de la imagen');
                }
            }

            $enCurso = Procesamiento::where('evidencia_id', $evidencia->id)
                ->whereIn('estado', ['pendiente', 'en_proceso'])
                ->exists();

            if ($enCurso) {
                $this->rechazar('Ya hay un análisis en curso para esta imagen');
            }

            if (Procesamiento::where('clave_operacion', $clave)->exists()) {
                $this->rechazar('Esta solicitud ya se registró');
            }

            $procesamiento = Procesamiento::create([
                'evidencia_id' => $evidencia->id,
                'configuracion_id' => $configuracion->id,
                'solicitado_por' => $usuario->id,
                'clave_operacion' => $clave,
                'estado' => 'pendiente',
                'intentos' => 0,
            ]);

            ProcesarConteo::dispatch($procesamiento->id)
                ->onQueue(config('inferencia.cola'))
                ->afterCommit();

            return $procesamiento;
        });
    }

    // Lo llama el trabajador de la cola
    public function ejecutar(int $id): void
    {
        // Se reclama de forma atomica: si otro trabajador ya lo tomo, no se repite
        $reclamado = Procesamiento::where('id', $id)
            ->where('estado', 'pendiente')
            ->update(['estado' => 'en_proceso', 'iniciado_en' => now(), 'intentos' => DB::raw('intentos + 1')]);

        if ($reclamado === 0) {
            return;
        }

        $procesamiento = Procesamiento::with(['evidencia.pase', 'configuracion'])->find($id);

        try {
            $this->procesar($procesamiento);
        } catch (\Throwable $e) {
            $this->falloInterno($id, $e);
        }
    }

    // Si algo inesperado rompe la ejecucion, queda un error visible y reintentable
    public function falloInterno(int $id, \Throwable $e): void
    {
        $procesamiento = Procesamiento::with('evidencia')->find($id);

        if (!$procesamiento || !in_array($procesamiento->estado, ['pendiente', 'en_proceso'])) {
            return;
        }

        $this->fallar($procesamiento, 'ERROR_INTERNO', class_basename($e) . ': ' . $e->getMessage());
    }

    private function procesar(Procesamiento $procesamiento): void
    {
        $evidencia = $procesamiento->evidencia;
        $configuracion = $procesamiento->configuracion;

        // Un resultado antiguo nunca sustituye a uno nuevo
        if ($evidencia->pase_lista_id !== null && $this->idVigente($evidencia->pase) !== $evidencia->id) {
            $this->fallar($procesamiento, 'EVIDENCIA_SUSTITUIDA', 'La imagen fue sustituida antes de analizarse', false);
            return;
        }

        $python = config('inferencia.python');
        $script = config('inferencia.script');

        if (!is_file($python)) {
            $this->fallar($procesamiento, 'PYTHON_NO_DISPONIBLE', 'No se encontró el intérprete de Python del proyecto');
            return;
        }

        if (!is_file($script)) {
            $this->fallar($procesamiento, 'SCRIPT_NO_DISPONIBLE', 'No se encontró el componente de inferencia');
            return;
        }

        // Rutas permitidas: la imagen dentro del almacenamiento privado y los pesos dentro de python/modelos
        $disco = $this->evidencias->disco();
        $rutaImagen = $this->rutaPermitida($disco->path($evidencia->ruta), $disco->path(''));
        $rutaPesos = $this->rutaPermitida(base_path($configuracion->yolo_pesos), base_path('python/modelos'));

        if (!$rutaImagen) {
            $this->fallar($procesamiento, 'IMAGEN_NO_ENCONTRADA', 'No se encontró el archivo de la imagen');
            return;
        }

        if (!$rutaPesos) {
            $this->fallar($procesamiento, 'PESOS_NO_ENCONTRADOS', 'No se encontraron los pesos de la configuración');
            return;
        }

        // SAM 3 solo se usa si la configuracion lo registro; si lo registro, sus pesos deben existir
        $usaSam = $this->usaSam($configuracion);
        $rutaSam = null;

        if ($usaSam) {
            $rutaSam = $this->rutaPermitida(base_path($configuracion->sam_pesos), base_path('python/modelos'));

            if (!$rutaSam) {
                $this->fallar($procesamiento, 'PESOS_SAM_NO_ENCONTRADOS', 'No se encontraron los pesos de SAM 3 de la configuración');
                return;
            }
        }

        $carpeta = 'resultados/' . $procesamiento->id;
        $disco->makeDirectory($carpeta);
        $directorio = $disco->path($carpeta);

        $rutaSolicitud = $directorio . DIRECTORY_SEPARATOR . 'solicitud.json';
        $rutaRespuesta = $directorio . DIRECTORY_SEPARATOR . 'respuesta.json';

        @unlink($rutaRespuesta);

        file_put_contents($rutaSolicitud, json_encode([
            'procesamiento_id' => $procesamiento->id,
            'evidencia_id' => $evidencia->id,
            'configuracion_id' => $configuracion->id,
            'ruta_imagen' => $rutaImagen,
            'pesos' => $rutaPesos,
            'directorio_resultados' => $directorio,
            'parametros' => collect($configuracion->parametros)->except('sam')->all(),
            'sam' => $usaSam ? array_merge(config('inferencia.sam'), $configuracion->parametros['sam'] ?? [], ['pesos' => $rutaSam]) : null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            // Argumentos separados, sin interprete de comandos
            $ejecucion = Process::timeout(config('inferencia.timeout'))
                ->path(base_path())
                ->env(['PYTHONIOENCODING' => 'utf-8', 'PYTHONUTF8' => '1'])
                ->run([$python, $script, '--solicitud', $rutaSolicitud, '--respuesta', $rutaRespuesta]);
        } catch (ProcessTimedOutException $e) {
            $this->fallar($procesamiento, 'TIEMPO_EXCEDIDO', 'El análisis superó el tiempo límite de ' . config('inferencia.timeout') . ' segundos');
            return;
        }

        if (!is_file($rutaRespuesta)) {
            $detalle = trim(mb_substr($ejecucion->errorOutput(), -300));
            $this->fallar($procesamiento, 'SIN_RESPUESTA', 'El componente terminó sin respuesta (código ' . $ejecucion->exitCode() . '). ' . $detalle);
            return;
        }

        $respuesta = json_decode(file_get_contents($rutaRespuesta), true);

        if (!is_array($respuesta)) {
            $this->fallar($procesamiento, 'RESPUESTA_INVALIDA', 'La respuesta del componente no es un JSON válido');
            return;
        }

        if (($respuesta['estado'] ?? null) === 'error') {
            $codigo = preg_replace('/[^A-Z0-9_]/', '', strtoupper((string) ($respuesta['codigo'] ?? 'ERROR'))) ?: 'ERROR';
            $this->fallar($procesamiento, mb_substr($codigo, 0, 40), (string) ($respuesta['mensaje'] ?? 'Error sin detalle'));
            return;
        }

        $problema = $this->validarRespuesta($respuesta, $procesamiento, $configuracion, $directorio);

        if ($problema) {
            $this->fallar($procesamiento, $problema[0], $problema[1]);
            return;
        }

        $this->guardarResultado($procesamiento, $respuesta, $carpeta);
    }

// Verificacion de Laravel: esquema, tipos, conteo, identificadores y huella de pesos
/**
 *Valida que la respuesta recibida del procesamiento sea correcta y que los datos obtenidos correspondan con la ejecución realizada.
 *
 *array $r Respuesta devuelta por el servicio de procesamiento.
 *Procesamiento $p Registro del procesamiento actual.
 *ConfiguracionModelo $c Configuración del modelo utilizado.
 *string $directorio Directorio donde se almacenan los archivos generados.
 *
 *return ?array Devuelve un arreglo con el código y mensaje de error, si alguna validación falla; de lo contrario, devuelve null.
 */
    private function validarRespuesta(array $r, Procesamiento $p, ConfiguracionModelo $c, string $directorio): ?array
    {
        if (($r['estado'] ?? null) !== 'procesado') { // Valida que el estado de la respuesta sea correcto.
            return ['RESPUESTA_INVALIDA', 'El estado de la respuesta no es válido'];
        }
        if ((int) ($r['procesamiento_id'] ?? 0) !== $p->id  // Verifica que los identificadores coincidan con el procesamiento actual.
            || (int) ($r['evidencia_id'] ?? 0) !== $p->evidencia_id
            || (int) ($r['configuracion_id'] ?? 0) !== $p->configuracion_id) {
            return ['IDENTIFICADORES_NO_COINCIDEN', 'La respuesta no corresponde a esta ejecución'];
        }
        if (!isset($r['conteo']) || !is_int($r['conteo']) || $r['conteo'] < 0) {// Comprueba que el conteo sea un entero no negativo.
            return ['CONTEO_INVALIDO', 'El conteo debe ser un entero no negativo'];
        }
        // Verifica que la cantidad de detecciones coincida con el conteo.
        if (!isset($r['detecciones']) || !is_array($r['detecciones']) || count($r['detecciones']) !== $r['conteo']) {
            return ['DETECCIONES_INVALIDAS', 'Las detecciones no coinciden con el conteo'];
        }
        foreach ($r['detecciones'] as $deteccion) {// Valida las coordenadas y confianza de cada detección.
            $caja = $deteccion['caja'] ?? null;
            $confianza = $deteccion['confianza'] ?? null;
            if (!is_array($caja) || count($caja) !== 4 || count(array_filter($caja, 'is_numeric')) !== 4) {// Comprueba que la caja tenga cuatro valores numéricos.
                return ['DETECCIONES_INVALIDAS', 'Una caja de detección no es válida'];
            }
            if (!is_numeric($confianza) || $confianza < 0 || $confianza > 1) { // Verifica que la confianza esté entre 0 y 1.
                return ['DETECCIONES_INVALIDAS', 'Una confianza de detección no es válida'];
            }
        }
        if (!isset($r['duracion_ms']) || !is_int($r['duracion_ms']) || $r['duracion_ms'] < 0) {// Comprueba que la duración sea un entero no negativo.
            return ['RESPUESTA_INVALIDA', 'La duración debe ser un entero no negativo'];
        }
        // Los pesos usados deben ser exactamente los de la configuracion
        $esperada = $c->huellas_pesos[basename($c->yolo_pesos)] ?? null;
        if (!$esperada || ($r['modelo']['sha256'] ?? null) !== $esperada) {// Verifica que los pesos utilizados coincidan con la configuración.
            return ['PESOS_NO_COINCIDEN', 'Los pesos utilizados no coinciden con la configuración registrada'];
        }
        // Comprueba que la imagen anotada tenga el nombre esperado y exista.
        $problemaSam = $this->validarSam($r, $c);

        if ($problemaSam) {
            return $problemaSam;
        }

        if (($r['anotada'] ?? null) !== null && ($r['anotada'] !== 'anotada.jpg' || !is_file($directorio . DIRECTORY_SEPARATOR . 'anotada.jpg'))) {
            return ['RESPUESTA_INVALIDA', 'La imagen con detecciones no es válida'];
        }
        return null;
    }

    private function usaSam(ConfiguracionModelo $c): bool
    {
        return $c->sam_pesos && $c->sam_pesos !== 'no aplicado';
    }

    // Si la configuracion usa SAM 3, la respuesta debe informar su estado; si lo aplico, deben
    // coincidir los pesos y cada deteccion debe traer su refinamiento completo
    private function validarSam(array $r, ConfiguracionModelo $c): ?array
    {
        if (!$this->usaSam($c)) {
            return null;
        }

        $sam = $r['sam'] ?? null;

        if (!is_array($sam) || !in_array($sam['estado'] ?? null, ['aplicado', 'omitido', 'error'], true)) {
            return ['RESPUESTA_INVALIDA', 'Falta el resultado de SAM 3 en la respuesta'];
        }

        if ($sam['estado'] !== 'aplicado') {
            return null;
        }

        $esperada = $c->huellas_pesos[basename($c->sam_pesos)] ?? null;

        if (!$esperada || ($sam['sha256'] ?? null) !== $esperada) {
            return ['PESOS_NO_COINCIDEN', 'Los pesos de SAM 3 utilizados no coinciden con la configuración registrada'];
        }

        foreach ($r['detecciones'] as $deteccion) {
            $ref = $deteccion['refinamiento'] ?? null;

            if (!is_array($ref)
                || !in_array($ref['estado'] ?? null, ['consistente', 'revisar'], true)
                || !is_array($ref['motivos'] ?? null)
                || !is_int($ref['area_mascara'] ?? null) || $ref['area_mascara'] < 0
                || !is_numeric($ref['relacion_mascara_caja'] ?? null)
                || !is_numeric($ref['solape_max'] ?? null)
                || !is_array($ref['poligono'] ?? null) || count($ref['poligono']) > 500) {
                return ['DETECCIONES_INVALIDAS', 'El refinamiento de SAM 3 de una detección no es válido'];
            }

            foreach ($ref['poligono'] as $punto) {
                if (!is_array($punto) || count($punto) !== 2 || !is_int($punto[0]) || !is_int($punto[1])) {
                    return ['DETECCIONES_INVALIDAS', 'Un polígono de SAM 3 no es válido'];
                }
            }
        }

        return null;
    }

    private function guardarResultado(Procesamiento $p, array $r, string $carpeta): void
    {
        DB::transaction(function () use ($p, $r, $carpeta) {
            $p->update([
                'estado' => 'procesado',
                'conteo' => $r['conteo'],
                'datos_resultado' => [
                    'detecciones' => $r['detecciones'],
                    'imagen' => $r['imagen'] ?? null,
                    'clase' => $r['clase'] ?? null,
                    'modelo' => $r['modelo'] ?? null,
                    'parametros' => $r['parametros'] ?? null,
                    'inferencia_ms' => $r['inferencia_ms'] ?? null,
                    'entorno' => $r['entorno'] ?? null,
                    'sam' => $r['sam'] ?? null,
                    'anotada' => ($r['anotada'] ?? null) ? $carpeta . '/anotada.jpg' : null,
                ],
                'terminado_en' => now(),
                'duracion_ms' => $r['duracion_ms'],
                'error' => null,
            ]);

            // Solo la ejecucion mas reciente de la imagen vigente actualiza el pase
            if ($this->esResultadoActual($p)) {
                PaseLista::whereKey($p->evidencia->pase_lista_id)->lockForUpdate()->first()
                    ->update(['estado' => 'procesado']);
            }
        });
    }

    private function fallar(Procesamiento $p, string $codigo, string $mensaje, bool $marcarPase = true): void
    {
        DB::transaction(function () use ($p, $codigo, $mensaje, $marcarPase) {
            $p->update([
                'estado' => 'error',
                'conteo' => null,
                'terminado_en' => now(),
                'error' => mb_substr($codigo . ': ' . $mensaje, 0, 1000),
            ]);

            // El pase queda "con incidencia": nunca se inventa un conteo
            if ($marcarPase && $this->esResultadoActual($p)) {
                $pase = PaseLista::whereKey($p->evidencia->pase_lista_id)->lockForUpdate()->first();
                $zona = config('app.timezone_local', 'America/Mexico_City');
                $nota = '[Análisis fallido · ' . now()->timezone($zona)->format('d/m/Y H:i') . '] ' . $codigo;

                $pase->update([
                    'estado' => 'con_incidencia',
                    'observaciones' => trim(($pase->observaciones ? $pase->observaciones . "\n" : '') . $nota),
                ]);
            }
        });
    }

    // Es el resultado actual si la imagen sigue vigente y no hay una ejecucion mas reciente
    private function esResultadoActual(Procesamiento $p): bool
    {
        $evidencia = $p->evidencia;

        // Las imagenes de evaluacion no pertenecen a ningun pase
        if ($evidencia->pase_lista_id === null) {
            return false;
        }

        if ($this->idVigente(PaseLista::find($evidencia->pase_lista_id)) !== $evidencia->id) {
            return false;
        }

        return !Procesamiento::where('evidencia_id', $evidencia->id)->where('id', '>', $p->id)->exists();
    }

    private function idVigente(?PaseLista $pase): ?int
    {
        if (!$pase) {
            return null;
        }

        $id = Evidencia::where('pase_lista_id', $pase->id)->orderByDesc('version')->value('id');

        return $id ? (int) $id : null;
    }

    // Se llama al abrir la pantalla de pases: si el trabajador estuvo apagado o murio a medias,
    // la ejecucion atascada se marca como interrumpida y vuelve a aparecer el boton de reintento
    public function expirarDeSesion(int $sesionId): void
    {
        $limite = now()->subSeconds(config('inferencia.timeout') + 300);

        $evidencias = Evidencia::whereHas('pase', function ($q) use ($sesionId) {
            $q->where('sesion_id', $sesionId);
        })->pluck('id');

        Procesamiento::whereIn('evidencia_id', $evidencias)
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->where('updated_at', '<', $limite)
            ->update([
                'estado' => 'error',
                'terminado_en' => now(),
                'error' => 'INTERRUMPIDO: El análisis no terminó. Puedes reintentarlo',
            ]);
    }

    // Una ejecucion que lleva demasiado tiempo sin avanzar se marca como interrumpida
    public function expirarAtascados(Evidencia $evidencia): void
    {
        $limite = now()->subSeconds(config('inferencia.timeout') + 300);

        Procesamiento::where('evidencia_id', $evidencia->id)
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->where('updated_at', '<', $limite)
            ->update([
                'estado' => 'error',
                'terminado_en' => now(),
                'error' => 'INTERRUMPIDO: El análisis no terminó. Puedes reintentarlo',
            ]);
    }

// Devuelve la ruta real solo si existe y esta dentro del directorio permitido
// Obtiene la ruta real únicamente si pertenece al directorio permitido.
    private function rutaPermitida(string $ruta, string $base): ?string
    {
    // Resuelve las rutas absolutas.
    $real = realpath($ruta);
    $raiz = realpath($base);

    // Verifica que ambas rutas existan.
    if (!$real || !$raiz) {
        return null;
    }

    // Comprueba que la ruta esté dentro del directorio permitido.
    return str_starts_with($real, $raiz . DIRECTORY_SEPARATOR) ? $real : null;
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['analisis' => $mensaje]);
    }
}
