<?php

namespace App\Console\Commands;

use App\Models\ConfiguracionModelo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class RegistrarModelo extends Command
{
    protected $signature = 'modelo:registrar
                            {pesos=python/modelos/yolov8s.pt : Ruta de los pesos, relativa a la carpeta del proyecto}
                            {--sam= : Pesos de SAM 3 (ruta relativa, por ejemplo python/modelos/sam3.pt). Sin esta opcion solo se usa YOLO}
                            {--nombre= : Etiqueta de la configuracion}
                            {--conf=0.25 : Umbral de confianza}
                            {--iou=0.7 : Umbral de solapamiento (NMS)}
                            {--imgsz=640 : Resolucion de entrada}';

    protected $description = 'Registra una configuracion del modelo YOLO con los pesos y el entorno efectivo de Python';

    public function handle(): int
    {
        $pesos = str_replace('\\', '/', $this->argument('pesos'));
        $ruta = realpath(base_path($pesos));
        $permitido = realpath(base_path('python/modelos'));

        // Los pesos deben estar dentro de python/modelos
        if (!$ruta || !$permitido || !str_starts_with($ruta, $permitido . DIRECTORY_SEPARATOR)) {
            $this->error('No se encontraron los pesos en python/modelos: ' . $pesos);
            return self::FAILURE;
        }

        $python = config('inferencia.python');

        if (!is_file($python)) {
            $this->error('No se encontró el intérprete de Python: ' . $python);
            return self::FAILURE;
        }

        // Pesos de SAM 3 (opcionales): tambien dentro de python/modelos
        $sam = $this->option('sam') ? str_replace('\\', '/', $this->option('sam')) : null;
        $rutaSam = null;

        if ($sam) {
            $rutaSam = realpath(base_path($sam));

            if (!$rutaSam || !str_starts_with($rutaSam, $permitido . DIRECTORY_SEPARATOR)) {
                $this->error('No se encontraron los pesos de SAM 3 en python/modelos: ' . $sam);
                return self::FAILURE;
            }

            $this->line('Calculando la huella de SAM 3 (pesa varios GB, puede tardar un minuto)...');
        }

        $respuesta = storage_path('app/private/info-modelo-' . uniqid() . '.json');

        $comando = [$python, config('inferencia.script'), '--info', '--pesos', $ruta, '--respuesta', $respuesta];

        if ($rutaSam) {
            array_push($comando, '--pesos-sam', $rutaSam);
        }

        $ejecucion = Process::timeout(600)
            ->path(base_path())
            ->env(['PYTHONIOENCODING' => 'utf-8', 'PYTHONUTF8' => '1'])
            ->run($comando);

        $info = is_file($respuesta) ? json_decode(file_get_contents($respuesta), true) : null;
        @unlink($respuesta);

        if (!is_array($info) || ($info['estado'] ?? null) !== 'ok') {
            $this->error('No se pudo leer la información del modelo: ' . ($info['mensaje'] ?? trim($ejecucion->errorOutput())));
            return self::FAILURE;
        }

        $parametros = array_merge(config('inferencia.parametros'), [
            'conf' => (float) $this->option('conf'),
            'iou' => (float) $this->option('iou'),
            'imgsz' => (int) $this->option('imgsz'),
        ]);

        $huellas = [basename($pesos) => $info['sha256']];

        if ($sam) {
            if (!isset($info['sam']['sha256'])) {
                $this->error('No se pudo leer la información de SAM 3');
                return self::FAILURE;
            }

            $huellas[basename($sam)] = $info['sam']['sha256'];
            $parametros['sam'] = config('inferencia.sam');
        }

        // Una configuracion usada es inmutable: cada registro nuevo crea otra fila
        $configuracion = ConfiguracionModelo::create([
            'nombre' => $this->option('nombre') ?: ($sam ? 'YOLO ' . basename($pesos) . ' + SAM 3' : 'YOLO ' . basename($pesos)) . ' · ' . now()->format('Y-m-d H:i'),
            'yolo_version' => $info['yolo_version'],
            'yolo_pesos' => $pesos,
            'sam_version' => $sam ? $info['sam']['version'] : 'no aplicado',
            'sam_pesos' => $sam ?: 'no aplicado',
            'huellas_pesos' => $huellas,
            'parametros' => $parametros,
            'entorno' => $info['entorno'],
            'activo' => 1,
        ]);

        $this->info('Configuración registrada con id ' . $configuracion->id);
        $this->line('  Modelo:     ' . $info['yolo_version'] . ' · ' . basename($pesos));
        $this->line('  SHA-256:    ' . $info['sha256']);

        if ($sam) {
            $this->line('  SAM 3:      ' . $info['sam']['version'] . ' · ' . basename($sam));
            $this->line('  SHA-256:    ' . $info['sam']['sha256']);
        }

        $this->line('  Python:     ' . $info['entorno']['python'] . ' · torch ' . ($info['entorno']['torch'] ?? '—') . ' · GPU: ' . ($info['entorno']['cuda'] ? $info['entorno']['gpu'] : 'no'));
        $this->line('  Parámetros: ' . json_encode($parametros));
        $this->line('Se usará la configuración activa más reciente en cada análisis nuevo.');

        return self::SUCCESS;
    }
}
