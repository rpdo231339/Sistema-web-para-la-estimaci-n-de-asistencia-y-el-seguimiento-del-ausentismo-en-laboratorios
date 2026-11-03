<?php

namespace App\Http\Controllers;

use App\Models\Evaluacion;
use App\Services\AuditoriaService;
use App\Services\EvaluacionService;
use App\Services\EvidenciaService;
use App\Services\InferenciaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EvaluacionController extends Controller
{
    public function index(Request $request)
    {
        $evaluaciones = $this->consulta($request)->get();

        // Solo las evaluaciones con resultado procesado entran en las metricas
        $filas = $evaluaciones
            ->filter(fn ($e) => $e->procesamiento->estado === 'procesado')
            ->map(fn ($e) => [
                'ambiente' => $e->ambiente,
                'oclusion' => $e->oclusion,
                'conteo' => $e->procesamiento->conteo,
                'referencia' => $e->referencia_manual,
                'tiempo_ms' => $e->procesamiento->duracion_ms,
                'omisiones' => $e->omisiones,
                'duplicaciones' => $e->duplicaciones,
            ])
            ->values()
            ->all();

        $general = EvaluacionService::resumen($filas);
        $porAmbiente = EvaluacionService::resumenPor($filas, 'ambiente');
        $porOclusion = EvaluacionService::resumenPor($filas, 'oclusion');

        $hayEnCurso = $evaluaciones->contains(fn ($e) => in_array($e->procesamiento->estado, ['pendiente', 'en_proceso']));

        return view('evaluaciones.index', compact('evaluaciones', 'general', 'porAmbiente', 'porOclusion', 'hayEnCurso'));
    }

    public function create()
    {
        return view('evaluaciones.create');
    }

    public function store(Request $request, EvaluacionService $servicio)
    {
        $maxKb = config('evidencias.max_kb');
        $maxMb = round($maxKb / 1024);
        $tiposMime = implode(',', array_keys(config('evidencias.mimes')));

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'mimetypes:' . $tiposMime, 'max:' . $maxKb],
            'ambiente' => 'required|in:aula,laboratorio',
            'iluminacion' => 'required|string|max:150',
            'angulo' => 'required|string|max:150',
            'oclusion' => 'required|in:nula,parcial,alta',
            'referencia_manual' => 'required|integer|min:0|max:1000',
            'observaciones' => 'nullable|string|max:1000',
            'clave_operacion' => ['required', 'regex:/^[a-f0-9]{64}$/'],
        ], [
            'archivo.required' => 'Selecciona una imagen',
            'archivo.uploaded' => "El archivo no se pudo cargar. Comprueba que no supere $maxMb MB",
            'archivo.mimes' => 'Formato no admitido: usa JPG, JPEG o PNG',
            'archivo.mimetypes' => 'El contenido del archivo no corresponde a una imagen JPG o PNG',
            'archivo.max' => "El archivo supera el límite de $maxMb MB",
            'ambiente.required' => 'Selecciona el ambiente',
            'iluminacion.required' => 'Describe la iluminación',
            'angulo.required' => 'Describe el ángulo de captura',
            'oclusion.required' => 'Selecciona el nivel de oclusión',
            'referencia_manual.required' => 'Indica cuántas personas cuentas tú en la imagen',
            'referencia_manual.integer' => 'La referencia debe ser un número entero',
            'referencia_manual.min' => 'La referencia no puede ser negativa',
            'clave_operacion.required' => 'La solicitud no es válida. Recarga la página e inténtalo de nuevo',
            'clave_operacion.regex' => 'La solicitud no es válida. Recarga la página e inténtalo de nuevo',
        ]);

        $evaluacion = $servicio->crear(
            $request->file('archivo'),
            $request->only(['ambiente', 'iluminacion', 'angulo', 'oclusion', 'referencia_manual', 'observaciones']),
            Auth::user(),
            $request->clave_operacion
        );

        return redirect()->route('evaluaciones.show', $evaluacion->id)
            ->with('success', 'Imagen registrada. El análisis se ejecuta en segundo plano y el resultado aparecerá en esta página');
    }

    public function show(Evaluacion $evaluacion, InferenciaService $inferencia)
    {
        $evaluacion->load(['procesamiento.evidencia', 'procesamiento.configuracion', 'registradoPor']);

        // Limpia un analisis atascado (trabajador apagado) para que reaparezca el boton de reintento
        $inferencia->expirarAtascados($evaluacion->procesamiento->evidencia);
        $evaluacion->load('procesamiento');

        return view('evaluaciones.show', compact('evaluacion'));
    }

    // Omisiones, duplicaciones y observaciones se capturan despues de mirar las detecciones
    public function update(Request $request, Evaluacion $evaluacion, AuditoriaService $auditoria)
    {
        $evaluacion->load('procesamiento');

        if ($evaluacion->procesamiento->estado !== 'procesado') {
            return back()->with('error', 'Solo se pueden registrar omisiones y duplicaciones cuando el análisis terminó');
        }

        $request->validate([
            'omisiones' => 'required|integer|min:0|max:1000',
            'duplicaciones' => 'required|integer|min:0|max:1000',
            'observaciones' => 'nullable|string|max:1000',
        ], [
            'omisiones.required' => 'Indica cuántas personas visibles no fueron detectadas (0 si ninguna)',
            'duplicaciones.required' => 'Indica cuántas detecciones están repetidas (0 si ninguna)',
        ]);

        DB::transaction(function () use ($request, $evaluacion, $auditoria) {
            $antes = $evaluacion->only(['omisiones', 'duplicaciones', 'observaciones']);

            $evaluacion->update([
                'omisiones' => $request->omisiones,
                'duplicaciones' => $request->duplicaciones,
                'observaciones' => $request->observaciones,
            ]);

            $auditoria->registrar(
                'evaluaciones',
                $evaluacion->id,
                'actualizacion',
                $antes,
                $evaluacion->only(['omisiones', 'duplicaciones', 'observaciones']),
                null
            );
        });

        return back()->with('success', 'Evaluación actualizada correctamente');
    }

    // Nuevo analisis de la misma imagen (por ejemplo, tras un error); la evaluacion apunta al resultado mas reciente
    public function analizar(Request $request, Evaluacion $evaluacion, InferenciaService $inferencia)
    {
        $request->validate([
            'clave_operacion' => ['required', 'regex:/^[a-f0-9]{64}$/'],
        ], [
            'clave_operacion.required' => 'La solicitud no es válida. Recarga la página e inténtalo de nuevo',
            'clave_operacion.regex' => 'La solicitud no es válida. Recarga la página e inténtalo de nuevo',
        ]);

        $evaluacion->load('procesamiento.evidencia');

        DB::transaction(function () use ($evaluacion, $inferencia, $request) {
            $nuevo = $inferencia->solicitar($evaluacion->procesamiento->evidencia, Auth::user(), $request->clave_operacion);

            $evaluacion->update(['procesamiento_id' => $nuevo->id]);
        });

        return back()->with('success', 'Análisis solicitado. Se ejecuta en segundo plano');
    }

    public function imagen(Evaluacion $evaluacion, EvidenciaService $servicio)
    {
        $evidencia = $evaluacion->procesamiento->evidencia;

        if (!$servicio->disco()->exists($evidencia->ruta)) {
            abort(404);
        }

        return $servicio->disco()->response($evidencia->ruta, $evidencia->nombre_original, [
            'Content-Type' => $evidencia->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    public function anotada(Evaluacion $evaluacion, EvidenciaService $servicio)
    {
        $procesamiento = $evaluacion->procesamiento;
        $ruta = data_get($procesamiento->datos_resultado, 'anotada');

        if ($procesamiento->estado !== 'procesado' || !$ruta || !$servicio->disco()->exists($ruta)) {
            abort(404);
        }

        return $servicio->disco()->response($ruta, 'detecciones-' . $procesamiento->id . '.jpg', [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, no-store',
        ]);
    }

    // CSV con los mismos filtros de la pantalla, para analizar los resultados fuera del sistema
    public function exportar(Request $request)
    {
        $evaluaciones = $this->consulta($request)->get();

        return response()->streamDownload(function () use ($evaluaciones) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");

            fputcsv($salida, [
                'id', 'fecha', 'ambiente', 'iluminacion', 'angulo', 'oclusion', 'referencia_manual',
                'conteo_automatico', 'diferencia', 'omisiones', 'duplicaciones', 'tiempo_ms', 'modelo', 'estado', 'observaciones',
            ]);

            foreach ($evaluaciones as $e) {
                $p = $e->procesamiento;

                fputcsv($salida, array_map([$this, 'celdaSegura'], [
                    $e->id,
                    $e->created_at->timezone(config('app.timezone_local', 'America/Mexico_City'))->format('Y-m-d H:i'),
                    $e->ambiente,
                    $e->iluminacion,
                    $e->angulo,
                    $e->oclusion,
                    $e->referencia_manual,
                    $p->estado === 'procesado' ? $p->conteo : '',
                    $e->diferencia() ?? '',
                    $e->omisiones ?? '',
                    $e->duplicaciones ?? '',
                    $p->estado === 'procesado' ? $p->duracion_ms : '',
                    data_get($p->datos_resultado, 'modelo.pesos', ''),
                    $p->estado,
                    $e->observaciones,
                ]));
            }

            fclose($salida);
        }, 'evaluaciones-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function consulta(Request $request)
    {
        $consulta = Evaluacion::with(['procesamiento', 'registradoPor'])->orderByDesc('id');

        if (in_array($request->ambiente, ['aula', 'laboratorio'], true)) {
            $consulta->where('ambiente', $request->ambiente);
        }

        if (in_array($request->oclusion, ['nula', 'parcial', 'alta'], true)) {
            $consulta->where('oclusion', $request->oclusion);
        }

        return $consulta;
    }

    // Evita que Excel interprete como formula un texto que empieza con = + - @
    private function celdaSegura($valor)
    {
        if (is_string($valor) && $valor !== '' && in_array($valor[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $valor;
        }

        return $valor;
    }
}
