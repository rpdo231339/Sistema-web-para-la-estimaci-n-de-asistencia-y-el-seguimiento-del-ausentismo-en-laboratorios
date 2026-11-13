<?php

namespace App\Http\Controllers;

use App\Models\Espacio;
use App\Models\Grupo;
use App\Models\PaseLista;
use App\Models\Procesamiento;
use App\Models\Respaldo;
use App\Models\Sesion;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class PanelController extends Controller
{
    // Tablero del inicio: pocos indicadores (maximo cuatro) y listas cortas, segun el rol
    public function index()
    {
        $usuario = Auth::user();
        $zona = config('app.timezone_local', 'America/Mexico_City');
        $ahoraLocal = Carbon::now($zona);

        // Los instantes se guardan en UTC: el dia local se convierte a su rango en UTC
        $inicioHoy = $ahoraLocal->copy()->startOfDay()->utc();
        $finHoy = $ahoraLocal->copy()->endOfDay()->utc();
        $inicioSemana = $ahoraLocal->copy()->subDays(6)->startOfDay()->utc();
        $hoy = $ahoraLocal->toDateString();

        // Cada rol ve solo lo suyo: el profesor sus sesiones, el encargado sus espacios, el administrador todo
        $espaciosIds = $usuario->rol === 'encargado'
            ? $usuario->espaciosAsignados()->pluck('espacios.id')->all()
            : [];

        $alcance = function ($q) use ($usuario, $espaciosIds) {
            $q->where('estado', '!=', 'cancelada');

            if ($usuario->rol === 'profesor') {
                $q->where('profesor_id', $usuario->id);
            } elseif ($usuario->rol === 'encargado') {
                $q->whereIn('espacio_id', $espaciosIds);
            }
        };

        $sesiones = fn () => Sesion::where($alcance);
        $pases = fn () => PaseLista::whereHas('sesion', $alcance);

        $sesionesHoy = $sesiones()->whereDate('fecha', $hoy)->count();
        $conIncidencia = $pases()->where('estado', 'con_incidencia')
            ->whereBetween('programado_en', [$inicioSemana, $finHoy])->count();

        $kpis = [];
        $listas = [];
        $accesos = [];
        $sistema = null;

        if ($usuario->rol === 'administrador') {
            $kpis = [
                $this->kpi('Usuarios activos', Usuario::where('activo', 1)->count(), 'fa-users', route('usuarios.index')),
                $this->kpi('Espacios activos', Espacio::where('activo', 1)->count(), 'fa-building', route('espacios.index')),
                $this->kpi('Grupos activos', Grupo::where('activo', 1)->count(), 'fa-people-group', route('grupos.index')),
                $this->kpi('Sesiones de hoy', $sesionesHoy, 'fa-calendar-day', route('reportes.index')),
            ];

            $listas[] = $this->listaSesiones('Sesiones de hoy', $sesiones()->whereDate('fecha', $hoy), null);

            $ultimoRespaldo = Respaldo::where('operacion', 'respaldo')->orderByDesc('id')->first();

            $sistema = [
                'respaldo' => $ultimoRespaldo,
                'errores' => Procesamiento::where('estado', 'error')->where('created_at', '>=', $inicioSemana)->count(),
                'en_curso' => Procesamiento::whereIn('estado', ['pendiente', 'en_proceso'])->count(),
                'zona' => $zona,
            ];

            $accesos = [
                ['Nuevo usuario', route('usuarios.create'), 'fa-user-plus', 'btn-success'],
                ['Generar respaldo', route('respaldos.index'), 'fa-database', 'btn-primary'],
                ['Ver reportes', route('reportes.index'), 'fa-chart-column', 'btn-secondary'],
            ];
        } elseif ($usuario->rol === 'encargado') {
            $porCargar = $pases()->where('estado', 'sin_evidencia')
                ->whereBetween('programado_en', [$inicioHoy, $finHoy]);

            $enAnalisis = Procesamiento::whereIn('estado', ['pendiente', 'en_proceso'])
                ->whereHas('evidencia.pase', fn ($q) => $q->whereHas('sesion', $alcance))->count();

            $kpis = [
                $this->kpi('Espacios asignados', count($espaciosIds), 'fa-building', route('espacios.index')),
                $this->kpi('Pases por cargar hoy', (clone $porCargar)->count(), 'fa-camera', route('pases.index')),
                $this->kpi('Análisis en curso', $enAnalisis, 'fa-gears', route('pases.index')),
                $this->kpi('Con incidencia (7 días)', $conIncidencia, 'fa-triangle-exclamation', route('pases.index'), $conIncidencia > 0, 'Requiere atención'),
            ];

            $proximos = (clone $porCargar)->with('sesion')->orderBy('programado_en')->limit(6)->get()
                ->map(function ($pase) {
                    $sesion = $pase->sesion;

                    return [
                        'hora' => $pase->programadoLocal()->format('H:i'),
                        'titulo' => data_get($sesion->contexto_historico, 'asignatura', 'Clase'),
                        'detalle' => PaseLista::MOMENTOS[$pase->momento] . ' · ' . data_get($sesion->contexto_historico, 'espacio.nombre', '—'),
                        'url' => route('pases.show', $sesion->id),
                    ];
                })->all();

            $listas[] = [
                'titulo' => 'Pases de lista de hoy por cargar',
                'icono' => 'fa-camera',
                'vacio' => 'No hay pases pendientes por cargar hoy.',
                'items' => $proximos,
            ];

            $accesos = [
                ['Pases de lista', route('pases.index'), 'fa-camera', 'btn-success'],
                ['Ver reportes', route('reportes.index'), 'fa-chart-column', 'btn-secondary'],
            ];
        } else {
            $porValidar = $pases()->where('estado', 'procesado');

            $sinConfirmar = $sesiones()->whereDate('fecha', '<=', $hoy)
                ->whereDoesntHave('asistencia', fn ($q) => $q->where('estado', 'confirmado'))->count();

            $kpis = [
                $this->kpi('Sesiones de hoy', $sesionesHoy, 'fa-calendar-day', route('sesiones.index')),
                $this->kpi('Conteos por validar', (clone $porValidar)->count(), 'fa-clipboard-check', route('sesiones.index'), false, null, 'es-acento'),
                $this->kpi('Asistencias por confirmar', $sinConfirmar, 'fa-user-check', route('sesiones.index')),
                $this->kpi('Con incidencia (7 días)', $conIncidencia, 'fa-triangle-exclamation', route('sesiones.index'), $conIncidencia > 0, 'Requiere atención'),
            ];

            $pendientes = (clone $porValidar)->with('sesion')->orderByDesc('programado_en')->limit(5)->get()
                ->map(function ($pase) {
                    $sesion = $pase->sesion;

                    return [
                        'hora' => $pase->programadoLocal()->format('d/m'),
                        'titulo' => data_get($sesion->contexto_historico, 'asignatura', 'Clase'),
                        'detalle' => PaseLista::MOMENTOS[$pase->momento] . ' · grupo ' . data_get($sesion->contexto_historico, 'grupo.clave', '—'),
                        'url' => route('sesiones.revision', [$sesion->id, $pase->id]),
                    ];
                })->all();

            $listas[] = $this->listaSesiones('Sesiones de hoy', $sesiones()->whereDate('fecha', $hoy), 'sesiones.show');
            $listas[] = [
                'titulo' => 'Conteos listos para validar',
                'icono' => 'fa-clipboard-check',
                'vacio' => 'No tienes conteos pendientes de validar.',
                'items' => $pendientes,
            ];

            $accesos = [
                ['Nueva sesión', route('sesiones.create'), 'fa-plus', 'btn-success'],
                ['Mis clases', route('clases.index'), 'fa-chalkboard-user', 'btn-primary'],
                ['Ver reportes', route('reportes.index'), 'fa-chart-column', 'btn-secondary'],
            ];
        }

        // Pases de los ultimos 7 dias por estado
        $estados = $pases()->whereBetween('programado_en', [$inicioSemana, $finHoy])
            ->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado')->all();

        $hora = (int) $ahoraLocal->format('G');
        $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

        return view('panel', [
            'saludo' => $saludo,
            'fecha' => ucfirst($ahoraLocal->locale('es')->isoFormat('dddd D [de] MMMM [de] YYYY')),
            'kpis' => $kpis,
            'listas' => $listas,
            'accesos' => $accesos,
            'estados' => $estados,
            'sistema' => $sistema,
        ]);
    }

    private function kpi(string $etiqueta, int $valor, string $icono, string $url, bool $alerta = false, ?string $nota = null, string $tono = ''): array
    {
        return compact('etiqueta', 'valor', 'icono', 'url', 'alerta', 'nota', 'tono');
    }

    // Lista corta de las sesiones de hoy, ordenadas por hora
    private function listaSesiones(string $titulo, $consulta, ?string $ruta): array
    {
        $items = $consulta->orderBy('hora_inicio')->limit(6)->get()->map(function ($sesion) use ($ruta) {
            return [
                'hora' => substr($sesion->hora_inicio, 0, 5) . '–' . substr($sesion->hora_fin, 0, 5),
                'titulo' => data_get($sesion->contexto_historico, 'asignatura', 'Clase'),
                'detalle' => 'Grupo ' . data_get($sesion->contexto_historico, 'grupo.clave', '—') . ' · ' . data_get($sesion->contexto_historico, 'espacio.nombre', '—'),
                'url' => $ruta ? route($ruta, $sesion->id) : null,
            ];
        })->all();

        return [
            'titulo' => $titulo,
            'icono' => 'fa-calendar-day',
            'vacio' => 'No hay sesiones programadas para hoy.',
            'items' => $items,
        ];
    }
}
