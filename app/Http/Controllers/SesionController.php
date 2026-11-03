<?php

namespace App\Http\Controllers;

use App\Models\EstimacionPresencia;
use App\Models\HorarioClase;
use App\Models\Sesion;
use App\Services\AusentismoService;
use App\Services\PaseListaService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SesionController extends Controller
{
    private const MENSAJE_SIN_PERMISO = 'No tienes permiso para gestionar esa sesión';

    public function index(Request $request)
    {
        // El profesor solo ve sus propias sesiones
        $consulta = Sesion::with(['clase', 'asistencia'])->where('profesor_id', Auth::id());

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;

            $consulta->whereHas('clase', function ($q) use ($buscar) {
                $q->where('asignatura', 'like', "%{$buscar}%");
            });
        }

        if ($request->filled('desde')) {
            $consulta->whereDate('fecha', '>=', $request->desde);
        }

        if ($request->filled('hasta')) {
            $consulta->whereDate('fecha', '<=', $request->hasta);
        }

        if ($request->filled('estado')) {
            $consulta->where('estado', $request->estado);
        }

        $sesiones = $consulta->orderByDesc('fecha')->orderByDesc('hora_inicio')->paginate(10)->withQueryString();

        return view('sesiones.index', compact('sesiones'));
    }

    public function create()
    {
        $bloques = $this->bloquesDisponibles();

        return view('sesiones.create', compact('bloques'));
    }

    public function store(Request $request, PaseListaService $pases)
    {
        $request->validate([
            'horario_clase_id' => 'required|integer',
            'fecha' => 'required|date_format:Y-m-d',
            'observaciones' => 'nullable|string|max:1000',
            'momentos' => 'required|array',
            'momentos.inicio' => 'required|date_format:H:i',
            'momentos.desarrollo' => 'required|date_format:H:i',
            'momentos.cierre' => 'required|date_format:H:i',
        ], [
            'horario_clase_id.required' => 'Selecciona el bloque de la clase',
            'fecha.date_format' => 'La fecha no es válida',
            'momentos.required' => 'Define las horas de los tres momentos del pase de lista',
            'momentos.*.date_format' => 'Escribe una hora válida (HH:MM)',
        ], [
            'horario_clase_id' => 'bloque',
            'momentos.inicio' => 'hora del momento de inicio',
            'momentos.desarrollo' => 'hora del momento de desarrollo',
            'momentos.cierre' => 'hora del momento de cierre',
        ]);

        $horario = HorarioClase::with(['clase.grupo', 'espacio'])->find($request->horario_clase_id);

        // Debe ser un bloque vigente de una clase vigente del profesor
        $valido = $horario
            && $horario->activo
            && $horario->clase->vigente
            && (int) $horario->clase->profesor_id === (int) Auth::id();

        if (!$valido) {
            throw ValidationException::withMessages(['horario_clase_id' => 'Selecciona un bloque de tus clases vigentes']);
        }

        // No se crean registros nuevos vinculados a catalogos inactivos
        if (!$horario->clase->grupo->activo) {
            throw ValidationException::withMessages(['horario_clase_id' => 'El grupo de esta clase está inactivo. Pide al administrador que lo reactive']);
        }

        if (!$horario->espacio->activo) {
            throw ValidationException::withMessages(['horario_clase_id' => 'El espacio de este bloque está inactivo. Pide que lo reactiven o cambia el bloque']);
        }

        $this->validarFecha($request->fecha, $horario);

        // Las tres horas deben ir en orden y dentro del horario de la sesion
        $pases->validarHoras($request->momentos, $horario->hora_inicio, $horario->hora_fin);

        $clase = $horario->clase;

        try {
            // La sesion y sus tres pases de lista se crean juntos o no se crea nada
            $sesion = DB::transaction(function () use ($request, $clase, $horario, $pases) {
                $sesion = Sesion::create([
                    'clase_id' => $clase->id,
                    'horario_clase_id' => $horario->id,
                    'fecha' => $request->fecha,
                    'profesor_id' => Auth::id(),
                    // Copia historica: editar la clase despues no altera esta sesion
                    'grupo_id' => $clase->grupo_id,
                    'espacio_id' => $horario->espacio_id,
                    'inscritos' => $clase->inscritos,
                    'hora_inicio' => $horario->hora_inicio,
                    'hora_fin' => $horario->hora_fin,
                    'periodo' => $clase->grupo->periodo,
                    'contexto_historico' => [
                        'asignatura' => $clase->asignatura,
                        'profesor' => Auth::user()->nombre,
                        'grupo' => [
                            'clave' => $clase->grupo->clave,
                            'programa' => $clase->grupo->programa,
                            'cuatrimestre' => $clase->grupo->cuatrimestre,
                        ],
                        'espacio' => [
                            'nombre' => $horario->espacio->nombre,
                            'tipo' => $horario->espacio->tipo,
                            'ubicacion' => $horario->espacio->ubicacion,
                        ],
                    ],
                    'estado' => 'programada',
                    'observaciones' => $request->observaciones,
                ]);

                $pases->programar($sesion, $request->momentos);

                return $sesion;
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['fecha' => 'Ya existe una sesión de este bloque en esa fecha']);
        }

        return redirect()->route('sesiones.show', $sesion->id)
            ->with('success', 'Sesión creada correctamente');
    }

    public function show(Sesion $sesion, AusentismoService $ausentismo)
    {
        if (!$this->esTitular($sesion)) {
            return redirect()->route('sesiones.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        $sesion->load(['clase', 'asistencia.confirmadoPor', 'pases.evidenciaVigente.procesamientoActual.revisionActual']);

        // Estimacion de presencia y ausentismo mas reciente (si existe) y si sigue vigente
        $estimacion = EstimacionPresencia::with('calculadoPor')
            ->where('sesion_id', $sesion->id)
            ->orderByDesc('id')
            ->first();

        $estimacionVigente = $estimacion
            && $estimacion->revisionesOrigen() == $ausentismo->revisionesVigentes($sesion);

        return view('sesiones.show', compact('sesion', 'estimacion', 'estimacionVigente'));
    }

    public function edit(Sesion $sesion)
    {
        if (!$this->esTitular($sesion)) {
            return redirect()->route('sesiones.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        if ($sesion->estado === 'cancelada') {
            return redirect()->route('sesiones.show', $sesion->id)
                ->with('error', 'Una sesión cancelada no se puede modificar. Reactívala primero');
        }

        $fechaBloqueada = $this->fechaBloqueada($sesion);

        return view('sesiones.edit', compact('sesion', 'fechaBloqueada'));
    }

    // Solo se reprograma la fecha y se editan las observaciones; el contexto historico no cambia
    public function update(Request $request, Sesion $sesion, PaseListaService $pases)
    {
        if (!$this->esTitular($sesion)) {
            return redirect()->route('sesiones.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        if ($sesion->estado === 'cancelada') {
            return redirect()->route('sesiones.show', $sesion->id)
                ->with('error', 'Una sesión cancelada no se puede modificar. Reactívala primero');
        }

        $request->validate([
            'fecha' => 'required|date_format:Y-m-d',
            'observaciones' => 'nullable|string|max:1000',
        ], [
            'fecha.date_format' => 'La fecha no es válida',
        ]);

        $cambioFecha = $request->fecha !== $sesion->fecha->format('Y-m-d');

        if ($cambioFecha) {
            // Con asistencia registrada, cambiar la fecha es un cambio de contexto que exige auditoria
            if ($this->fechaBloqueada($sesion)) {
                throw ValidationException::withMessages([
                    'fecha' => 'Esta sesión ya tiene datos registrados y su fecha no se puede cambiar. Cancélala y crea otra',
                ]);
            }

            $this->validarFecha($request->fecha, $sesion->horario, $sesion);
        }

        try {
            DB::transaction(function () use ($request, $sesion, $cambioFecha, $pases) {
                $sesion->update([
                    'fecha' => $request->fecha,
                    'observaciones' => $request->observaciones,
                ]);

                // Los tres momentos conservan su hora local en la fecha nueva
                if ($cambioFecha) {
                    $pases->moverFecha($sesion, $request->fecha);
                }
            });
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['fecha' => 'Ya existe una sesión de este bloque en esa fecha']);
        }

        return redirect()->route('sesiones.show', $sesion->id)
            ->with('success', 'Sesión actualizada correctamente');
    }

    // Cancelacion con motivo obligatorio; la sesion cancelada se excluye de los indicadores
    public function cancelar(Request $request, Sesion $sesion)
    {
        if (!$this->esTitular($sesion)) {
            return redirect()->route('sesiones.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        $request->validate([
            'motivo_cancelacion' => 'required|string|max:500',
        ], [
            'motivo_cancelacion.required' => 'Escribe el motivo de la cancelación',
        ]);

        if ($sesion->estado === 'cancelada') {
            return back()->with('error', 'La sesión ya está cancelada');
        }

        $asistencia = $sesion->asistencia;

        if ($asistencia && $asistencia->estado === 'confirmado') {
            return back()->with('error', 'Reabre la asistencia para revisión antes de cancelar la sesión');
        }

        $nota = 'Cancelación: ' . $request->motivo_cancelacion;

        $sesion->update([
            'estado' => 'cancelada',
            'observaciones' => trim(($sesion->observaciones ? $sesion->observaciones . "\n" : '') . $nota),
        ]);

        return back()->with('success', 'Sesión cancelada correctamente');
    }

    // Necesaria porque UNIQUE(horario_clase_id, fecha) deja ocupada la fecha de una sesion cancelada
    public function reactivar(Sesion $sesion)
    {
        if (!$this->esTitular($sesion)) {
            return redirect()->route('sesiones.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        if ($sesion->estado !== 'cancelada') {
            return back()->with('error', 'Solo se pueden reactivar sesiones canceladas');
        }

        $sesion->update(['estado' => 'programada']);

        return back()->with('success', 'Sesión reactivada correctamente');
    }

    // Define (o cambia) la hora de los tres momentos; tambien sirve para sesiones anteriores sin pases
    public function programarPases(Request $request, Sesion $sesion, PaseListaService $pases)
    {
        if (!$this->esTitular($sesion)) {
            return redirect()->route('sesiones.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        if ($sesion->estado === 'cancelada') {
            return back()->with('error', 'La sesión está cancelada. Reactívala para programar sus pases de lista');
        }

        $request->validate([
            'momentos' => 'required|array',
            'momentos.inicio' => 'required|date_format:H:i',
            'momentos.desarrollo' => 'required|date_format:H:i',
            'momentos.cierre' => 'required|date_format:H:i',
        ], [
            'momentos.required' => 'Define las horas de los tres momentos del pase de lista',
            'momentos.*.date_format' => 'Escribe una hora válida (HH:MM)',
        ], [
            'momentos.inicio' => 'hora del momento de inicio',
            'momentos.desarrollo' => 'hora del momento de desarrollo',
            'momentos.cierre' => 'hora del momento de cierre',
        ]);

        $pases->validarHoras($request->momentos, $sesion->hora_inicio, $sesion->hora_fin);

        DB::transaction(function () use ($sesion, $request, $pases) {
            $pases->programar($sesion, $request->momentos);
        });

        return back()->with('success', 'Pases de lista programados correctamente');
    }

    // Bloques vigentes de clases vigentes del profesor, con grupo y espacio activos
    private function bloquesDisponibles()
    {
        return HorarioClase::with(['clase.grupo', 'espacio'])
            ->where('activo', 1)
            ->whereHas('clase', function ($q) {
                $q->where('profesor_id', Auth::id())
                  ->where('vigente', 1)
                  ->whereHas('grupo', function ($g) {
                      $g->where('activo', 1);
                  });
            })
            ->whereHas('espacio', function ($e) {
                $e->where('activo', 1);
            })
            ->get()
            ->sortBy(function ($h) {
                return $h->clase->asignatura . $h->dia . $h->hora_inicio;
            })
            ->values();
    }

    // La fecha debe caer en el dia del bloque y no repetirse para ese bloque
    private function validarFecha(string $fecha, HorarioClase $horario, ?Sesion $ignorar = null): void
    {
        $dia = Carbon::createFromFormat('Y-m-d', $fecha)->dayOfWeekIso;

        if ($dia !== (int) $horario->dia) {
            throw ValidationException::withMessages([
                'fecha' => 'La fecha elegida es ' . mb_strtolower(HorarioClase::DIAS[$dia])
                    . ' y este bloque es de ' . mb_strtolower(HorarioClase::DIAS[(int) $horario->dia]),
            ]);
        }

        $consulta = Sesion::where('horario_clase_id', $horario->id)->whereDate('fecha', $fecha);

        if ($ignorar) {
            $consulta->where('id', '<>', $ignorar->id);
        }

        if ($consulta->exists()) {
            throw ValidationException::withMessages([
                'fecha' => 'Ya existe una sesión de este bloque en esa fecha',
            ]);
        }
    }

    // La fecha se congela cuando la sesion ya se realizo o tiene asistencia registrada
    private function fechaBloqueada(Sesion $sesion): bool
    {
        return $sesion->estado !== 'programada' || $sesion->asistencia()->exists();
    }

    private function esTitular(Sesion $sesion): bool
    {
        return (int) $sesion->profesor_id === (int) Auth::id();
    }
}
