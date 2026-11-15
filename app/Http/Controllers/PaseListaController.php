<?php

namespace App\Http\Controllers;

use App\Models\PaseLista;
use App\Models\Sesion;
use App\Services\EvidenciaService;
use App\Services\InferenciaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaseListaController extends Controller
{
    private const MENSAJE_SIN_PERMISO = 'No tienes permiso para gestionar los pases de esa sesión';

    public function index(Request $request)
    {
        $usuario = Auth::user();

        // El encargado solo ve sesiones de los espacios que tiene asignados
        $espacios = $usuario->espaciosAsignados()->orderBy('nombre')->get();

        $consulta = Sesion::with(['clase', 'profesor', 'pases.evidenciaVigente.procesamientoActual'])
            ->whereIn('espacio_id', $espacios->pluck('id'))
            ->where('estado', '<>', 'cancelada');

        if ($request->filled('espacio_id')) {
            $consulta->where('espacio_id', $request->espacio_id);
        }

        if ($request->filled('desde')) {
            $consulta->whereDate('fecha', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $consulta->whereDate('fecha', '<=', $request->hasta);
        }

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;

            $consulta->where(function ($q) use ($buscar) {
                $q->whereHas('clase', function ($c) use ($buscar) {
                    $c->where('asignatura', 'like', "%{$buscar}%");
                })->orWhereHas('profesor', function ($p) use ($buscar) {
                    $p->where('nombre', 'like', "%{$buscar}%");
                });
            });
        }

        $sesiones = $consulta->orderByDesc('fecha')->orderBy('hora_inicio')->paginate(10)->withQueryString();

        return view('pases.index', compact('sesiones', 'espacios'));
    }

    public function show(Sesion $sesion, InferenciaService $inferencia)
    {
        if (!$this->autorizado($sesion)) {
            return redirect()->route('pases.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        // Limpia los analisis que quedaron atascados (trabajador apagado o caido)
        $inferencia->expirarDeSesion($sesion->id);

        $sesion->load(['clase', 'profesor', 'pases.evidencias.cargadoPor', 'pases.evidenciaVigente.procesamientoActual']);

        return view('pases.show', compact('sesion'));
    }

    // Carga una imagen para un momento; si ya habia una, esta pasa a ser una version anterior
    public function subir(Request $request, Sesion $sesion, EvidenciaService $evidencias)
    {
        if (!$this->autorizado($sesion)) {
            return redirect()->route('pases.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        if ($sesion->estado === 'cancelada') {
            return back()->with('error', 'La sesión está cancelada y no admite evidencias');
        }

        $maxKb = config('evidencias.max_kb');
        $maxMb = round($maxKb / 1024);
        $tiposMime = implode(',', array_keys(config('evidencias.mimes')));

        $request->validate([
            'momento' => 'required|in:inicio,desarrollo,cierre',
            'archivo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'mimetypes:' . $tiposMime, 'max:' . $maxKb],
            'capturada_en' => 'required|date_format:Y-m-d\TH:i',
            'observaciones' => 'nullable|string|max:1000',
        ], [
            'momento.required' => 'Selecciona el momento',
            'momento.in' => 'El momento no es válido',
            'archivo.required' => 'Selecciona un archivo',
            'archivo.uploaded' => "El archivo no se pudo cargar. Comprueba que no supere $maxMb MB",
            'archivo.mimes' => 'Formato no admitido: usa JPG, JPEG o PNG',
            'archivo.mimetypes' => 'El contenido del archivo no corresponde a una imagen JPG o PNG',
            'archivo.max' => "El archivo supera el límite de $maxMb MB",
            'capturada_en.required' => 'Indica la fecha y hora que representa la imagen',
            'capturada_en.date_format' => 'La fecha y hora no son válidas',
        ], [
            'archivo' => 'archivo',
            'capturada_en' => 'hora representada',
        ]);

        $pase = $sesion->pases()->where('momento', $request->momento)->first();

        if (!$pase) {
            throw ValidationException::withMessages([
                'momento' => 'Ese momento todavía no está programado. El profesor debe definir las horas de los tres momentos',
            ]);
        }

        $zona = config('app.timezone_local', 'America/Mexico_City');
        $capturadaLocal = Carbon::createFromFormat('Y-m-d\TH:i', $request->capturada_en, $zona);

        // La imagen debe representar la fecha de la sesion y no una hora futura
        if ($capturadaLocal->format('Y-m-d') !== $sesion->fecha->format('Y-m-d')) {
            throw ValidationException::withMessages([
                'capturada_en' => 'La hora representada debe caer en la fecha de la sesión (' . $sesion->fecha->format('d/m/Y') . ')',
            ]);
        }

        if (config('evidencias.rechazar_futuro', true) && $capturadaLocal->copy()->utc()->gt(now()->addMinutes(5))) {
            throw ValidationException::withMessages([
                'capturada_en' => 'La hora representada no puede estar en el futuro',
            ]);
        }

        // Archivo danado o de tipo no admitido: se rechaza indicando la causa
        $imagen = $evidencias->leerImagen($request->file('archivo'));

        $evidencia = $evidencias->guardarVersion(
            $pase,
            $request->file('archivo'),
            $imagen,
            $capturadaLocal->copy()->utc(),
            Auth::user(),
            $request->observaciones
        );

        $etiqueta = PaseLista::MOMENTOS[$pase->momento];

        return redirect()->route('pases.show', $sesion->id)
            ->with('success', "Imagen del momento de $etiqueta cargada como versión {$evidencia->version}");
    }

    // Solicita el analisis de la imagen vigente: se ejecuta en segundo plano
    public function analizar(Request $request, PaseLista $pase, InferenciaService $inferencia)
    {
        $sesion = $pase->sesion;

        if (!$this->autorizado($sesion)) {
            return redirect()->route('pases.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        if ($sesion->estado === 'cancelada') {
            return back()->with('error', 'La sesión está cancelada y no admite análisis');
        }

        $request->validate([
            'clave_operacion' => ['required', 'regex:/^[a-f0-9]{64}$/'],
        ], [
            'clave_operacion.required' => 'La solicitud no es válida. Recarga la página e inténtalo de nuevo',
            'clave_operacion.regex' => 'La solicitud no es válida. Recarga la página e inténtalo de nuevo',
        ]);

        $vigente = $pase->evidenciaVigente;

        if (!$vigente) {
            return back()->with('error', 'Este momento no tiene una imagen cargada');
        }

        $inferencia->solicitar($vigente, Auth::user(), $request->clave_operacion);

        return back()->with('success', 'Análisis solicitado. Se ejecuta en segundo plano y el resultado aparecerá en esta página');
    }

    // Una imagen con visibilidad insuficiente se marca para revision; nunca se toma como aula vacia
    public function marcarVisibilidad(Request $request, PaseLista $pase)
    {
        $sesion = $pase->sesion;

        if (!$this->autorizado($sesion)) {
            return redirect()->route('pases.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        $request->validate([
            'motivo' => 'required|string|max:500',
        ], [
            'motivo.required' => 'Describe por qué la imagen no tiene visibilidad suficiente',
        ]);

        $pase->load('evidenciaVigente');

        if (!$pase->evidenciaVigente) {
            return back()->with('error', 'Este momento no tiene una imagen cargada');
        }

        if ($pase->estado !== 'sin_evidencia') {
            return back()->with('error', 'Este momento ya tiene un resultado y no se puede marcar');
        }

        $zona = config('app.timezone_local', 'America/Mexico_City');
        $nota = '[Visibilidad insuficiente · ' . now()->timezone($zona)->format('d/m/Y H:i') . '] ' . $request->motivo;

        $pase->update([
            'estado' => 'con_incidencia',
            'observaciones' => trim(($pase->observaciones ? $pase->observaciones . "\n" : '') . $nota),
        ]);

        return back()->with('success', 'Imagen marcada para revisión. No se interpreta como un aula vacía: sube una versión nueva si puedes repetirla');
    }

    // El encargado debe tener asignado el espacio de la sesion
    private function autorizado(Sesion $sesion): bool
    {
        return Auth::user()->espaciosAsignados()->where('espacios.id', $sesion->espacio_id)->exists();
    }
}
