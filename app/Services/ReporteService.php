<?php

namespace App\Services;

use App\Models\EstimacionPresencia;
use App\Models\Espacio;
use App\Models\Grupo;
use App\Models\Sesion;
use App\Models\Usuario;

class ReporteService
{
    // Tipos del reporte de incidencias y pendientes (FN.17)
    public const TIPOS = [
        'momento_sin_evidencia' => 'Momento sin evidencia',
        'validacion_pendiente' => 'Validación pendiente',
        'error_analisis' => 'Error de análisis',
        'imagen_no_visible' => 'Imagen no visible',
        'resultado_observado' => 'Resultado observado',
        'asistencia_pendiente' => 'Asistencia sin confirmar',
    ];

    public function __construct(private AusentismoService $ausentismo)
    {
    }

    // Sesiones que el usuario puede ver, con los filtros aplicados. Las canceladas nunca cuentan.
    public function consulta(Usuario $usuario, array $filtros = [])
    {
        $consulta = Sesion::query()->where('estado', '!=', 'cancelada');

        if ($usuario->rol === 'profesor') {
            $consulta->where('profesor_id', $usuario->id);
        } elseif ($usuario->rol === 'encargado') {
            $consulta->whereIn('espacio_id', $usuario->espaciosAsignados()->pluck('espacios.id'));
        } elseif ($usuario->rol !== 'administrador') {
            $consulta->whereRaw('1 = 0');
        }

        if (!empty($filtros['periodo'])) {
            $consulta->where('periodo', $filtros['periodo']);
        }

        if (!empty($filtros['desde'])) {
            $consulta->whereDate('fecha', '>=', $filtros['desde']);
        }

        if (!empty($filtros['hasta'])) {
            $consulta->whereDate('fecha', '<=', $filtros['hasta']);
        }

        if (!empty($filtros['grupo_id'])) {
            $consulta->where('grupo_id', $filtros['grupo_id']);
        }

        if (!empty($filtros['espacio_id'])) {
            $consulta->where('espacio_id', $filtros['espacio_id']);
        }

        return $consulta;
    }

    // Opciones de los filtros: solo lo que aparece en sesiones que el usuario puede ver
    public function opciones(Usuario $usuario): array
    {
        $base = $this->consulta($usuario);

        return [
            'periodos' => (clone $base)->select('periodo')->distinct()->orderByDesc('periodo')->pluck('periodo'),
            'grupos' => Grupo::whereIn('id', (clone $base)->select('grupo_id')->distinct())->orderBy('clave')->get(['id', 'clave', 'programa']),
            'espacios' => Espacio::whereIn('id', (clone $base)->select('espacio_id')->distinct())->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    /**
     * Porcentaje con dos decimales (half-up) calculado con enteros.
     * Los porcentajes de varias sesiones o clases se calculan siempre sobre la suma de cantidades,
     * nunca promediando porcentajes de grupos de tamanos distintos.
     */
    public static function porcentaje(int $numerador, int $denominador): ?string
    {
        if ($denominador <= 0) {
            return null;
        }

        $centesimas = intdiv(2 * $numerador * 10000 + $denominador, 2 * $denominador);

        return sprintf('%d.%02d', intdiv($centesimas, 100), $centesimas % 100);
    }

    // FN.14: una fila por clase con las cantidades confirmadas por el profesor
    public function porClase(Usuario $usuario, array $filtros): array
    {
        $sesiones = $this->consulta($usuario, $filtros)->with('asistencia')->orderBy('fecha')->orderBy('hora_inicio')->get();

        $filas = [];

        foreach ($sesiones->groupBy('clase_id') as $grupoSesiones) {
            $filas[] = $this->filaClase($grupoSesiones);
        }

        usort($filas, fn ($a, $b) => strcmp($a['asignatura'] . $a['grupo'], $b['asignatura'] . $b['grupo']));

        return [
            'filas' => $filas,
            'total' => $this->filaClase($sesiones, true),
        ];
    }

    private function filaClase($sesiones, bool $total = false): array
    {
        $ultima = $sesiones->last();
        $contexto = $ultima ? ($ultima->contexto_historico ?? []) : [];

        // Solo cuentan las sesiones con asistencia confirmada
        $confirmadas = $sesiones->filter(fn ($s) => $s->asistencia && $s->asistencia->estado === 'confirmado');

        $inscritos = (int) $confirmadas->sum('inscritos');
        $asistentes = (int) $confirmadas->sum(fn ($s) => (int) $s->asistencia->asistentes);
        $justificados = (int) $confirmadas->sum(fn ($s) => (int) $s->asistencia->justificados);
        $faltas = (int) $confirmadas->sum(fn ($s) => (int) $s->asistencia->faltas);

        return [
            'asignatura' => $total ? 'TOTAL' : ($contexto['asignatura'] ?? '—'),
            'grupo' => $total ? '' : data_get($contexto, 'grupo.clave', '—'),
            'profesor' => $total ? '' : ($contexto['profesor'] ?? '—'),
            'periodo' => $total ? '' : ($ultima->periodo ?? '—'),
            'sesiones' => $sesiones->count(),
            'confirmadas' => $confirmadas->count(),
            'inscritos' => $inscritos,
            'asistentes' => $asistentes,
            'justificados' => $justificados,
            'faltas' => $faltas,
            'asistencia_pct' => self::porcentaje($asistentes, $inscritos),
            'ausencia_pct' => self::porcentaje($justificados + $faltas, $inscritos),
            'faltas_pct' => self::porcentaje($faltas, $inscritos),
        ];
    }

    // FN.16: comparativo entre grupos, agrupando por grupo y periodo
    public function porGrupo(Usuario $usuario, array $filtros): array
    {
        $sesiones = $this->consulta($usuario, $filtros)->with('asistencia')->orderBy('fecha')->get();

        $filas = [];

        foreach ($sesiones->groupBy(fn ($s) => $s->grupo_id . '|' . $s->periodo) as $grupoSesiones) {
            $ultima = $grupoSesiones->last();
            $contexto = $ultima->contexto_historico ?? [];

            $fila = $this->filaClase($grupoSesiones);
            $fila['grupo'] = data_get($contexto, 'grupo.clave', '—');
            $fila['programa'] = data_get($contexto, 'grupo.programa', '—');
            $fila['periodo'] = $ultima->periodo;
            $fila['clases'] = $grupoSesiones->pluck('clase_id')->unique()->count();

            $filas[] = $fila;
        }

        // Periodo mas reciente primero; dentro del periodo, por clave de grupo
        usort($filas, fn ($a, $b) => [$b['periodo'], $a['grupo']] <=> [$a['periodo'], $b['grupo']]);

        return [
            'filas' => $filas,
            'total' => $this->filaClase($sesiones, true),
        ];
    }

    // FN.17: incidencias y pendientes, calculados a partir de los estados registrados
    public function incidencias(Usuario $usuario, array $filtros, int $limite = 1000): array
    {
        $sesiones = $this->consulta($usuario, $filtros)
            ->with(['asistencia', 'pases.evidenciaVigente.procesamientoActual.revisionActual'])
            ->orderByDesc('fecha')->orderByDesc('hora_inicio')
            ->limit($limite + 1)
            ->get();

        $truncado = $sesiones->count() > $limite;
        $sesiones = $sesiones->take($limite);

        $zona = config('app.timezone_local', 'America/Mexico_City');
        $hoy = now()->timezone($zona)->toDateString();
        $items = [];

        foreach ($sesiones as $s) {
            $contexto = $s->contexto_historico ?? [];
            $base = [
                'sesion_id' => $s->id,
                'fecha' => $s->fecha->format('Y-m-d'),
                'asignatura' => $contexto['asignatura'] ?? '—',
                'grupo' => data_get($contexto, 'grupo.clave', '—'),
                'espacio' => data_get($contexto, 'espacio.nombre', '—'),
            ];

            foreach ($s->pases as $pase) {
                $momento = \App\Models\PaseLista::MOMENTOS[$pase->momento] ?? ucfirst($pase->momento);
                $proc = optional($pase->evidenciaVigente)->procesamientoActual;

                if ($pase->estado === 'sin_evidencia') {
                    // Solo es pendiente si la hora del momento ya paso
                    if (\Carbon\Carbon::parse($pase->programado_en)->lt(now())) {
                        $items[] = $base + $this->item('momento_sin_evidencia', 'pendiente', $momento, 'No se ha cargado la imagen de este momento');
                    }
                } elseif ($pase->estado === 'procesado') {
                    $items[] = $base + $this->item('validacion_pendiente', 'pendiente', $momento, 'Resultado procesado (conteo automático ' . ($proc->conteo ?? '—') . ') pendiente de validación del profesor');
                } elseif ($pase->estado === 'con_incidencia') {
                    $revision = $proc ? $proc->revisionActual : null;

                    if ($proc && $proc->estado === 'error') {
                        $items[] = $base + $this->item('error_analisis', 'incidencia', $momento, \Illuminate\Support\Str::limit((string) $proc->error, 200));
                    } elseif ($revision && $revision->decision === 'observado') {
                        $items[] = $base + $this->item('resultado_observado', 'incidencia', $momento, \Illuminate\Support\Str::limit((string) $revision->motivo, 200));
                    } else {
                        $lineas = array_filter(explode("\n", (string) $pase->observaciones));
                        $items[] = $base + $this->item('imagen_no_visible', 'incidencia', $momento, \Illuminate\Support\Str::limit((string) end($lineas), 200));
                    }
                }
            }

            // Una sesion que ya paso sin asistencia confirmada es un pendiente del profesor
            $asistencia = $s->asistencia;

            if ($s->fecha->format('Y-m-d') < $hoy && (!$asistencia || $asistencia->estado !== 'confirmado')) {
                $estadoTexto = $asistencia
                    ? ['borrador' => 'en borrador', 'pendiente_revision' => 'pendiente de revisión'][$asistencia->estado]
                    : 'sin registrar';

                $items[] = $base + $this->item('asistencia_pendiente', 'pendiente', '—', 'La asistencia de la sesión está ' . $estadoTexto);
            }
        }

        if (!empty($filtros['tipo'])) {
            $items = array_values(array_filter($items, fn ($i) => $i['tipo'] === $filtros['tipo']));
        }

        $resumen = array_fill_keys(array_keys(self::TIPOS), 0);
        foreach ($items as $i) {
            $resumen[$i['tipo']]++;
        }

        return [
            'filas' => $items,
            'resumen' => $resumen,
            'total' => count($items),
            'truncado' => $truncado,
            'limite' => $limite,
        ];
    }

    private function item(string $tipo, string $estado, string $momento, string $detalle): array
    {
        return [
            'tipo' => $tipo,
            'tipo_texto' => self::TIPOS[$tipo],
            'estado' => $estado,
            'estado_texto' => $estado === 'pendiente' ? 'Pendiente' : 'Incidencia',
            'momento' => $momento,
            'detalle' => $detalle,
        ];
    }

    // FN.15: una fila por sesion con los tres momentos, en orden cronologico
    public function momentos(Usuario $usuario, array $filtros, int $limite = 1000): array
    {
        $sesiones = $this->consulta($usuario, $filtros)
            ->with(['pases.evidenciaVigente.procesamientoActual.revisionActual'])
            ->orderBy('fecha')->orderBy('hora_inicio')
            ->limit($limite + 1)
            ->get();

        $truncado = $sesiones->count() > $limite;
        $sesiones = $sesiones->take($limite);

        $estimaciones = EstimacionPresencia::whereIn('sesion_id', $sesiones->pluck('id'))
            ->orderBy('id')->get()->groupBy('sesion_id')->map(fn ($g) => $g->last());

        $filas = [];
        $completas = 0;

        foreach ($sesiones as $s) {
            $contexto = $s->contexto_historico ?? [];
            $porMomento = $s->pases->keyBy('momento');

            $celdas = [];
            foreach (AusentismoService::MOMENTOS as $m) {
                $celdas[$m] = $this->celdaMomento($porMomento->get($m));
            }

            if (collect($celdas)->every(fn ($c) => $c['tipo'] === 'validado')) {
                $completas++;
            }

            $estimacion = $estimaciones->get($s->id);
            $estado = 'sin';

            if ($estimacion) {
                $estado = $estimacion->revisionesOrigen() == $this->ausentismo->revisionesVigentes($s) ? 'vigente' : 'desactualizada';
            }

            $filas[] = [
                'fecha' => $s->fecha->format('Y-m-d'),
                'horario' => substr($s->hora_inicio, 0, 5) . '–' . substr($s->hora_fin, 0, 5),
                'asignatura' => $contexto['asignatura'] ?? '—',
                'grupo' => data_get($contexto, 'grupo.clave', '—'),
                'espacio' => data_get($contexto, 'espacio.nombre', '—'),
                'inscritos' => (int) $s->inscritos,
                'momentos' => $celdas,
                'media' => $estado === 'vigente' ? number_format((float) $estimacion->media, 2) : null,
                'ausentismo' => $estado === 'vigente' ? number_format((float) $estimacion->ausentismo, 2) : null,
                'estimacion' => $estado,
            ];
        }

        return [
            'filas' => $filas,
            'sesiones' => count($filas),
            'completas' => $completas,
            'truncado' => $truncado,
            'limite' => $limite,
        ];
    }

    // Un momento sin evidencia nunca se muestra como cero: se identifica de forma explicita
    private function celdaMomento($pase): array
    {
        if (!$pase) {
            return ['tipo' => 'sin_programar', 'valor' => null, 'texto' => 'No programado'];
        }

        if ($pase->estado === 'validado') {
            $proc = optional($pase->evidenciaVigente)->procesamientoActual;
            $rev = $proc ? $proc->revisionActual : null;

            if ($rev && $rev->decision === 'validado' && $rev->conteo_revisado !== null) {
                return ['tipo' => 'validado', 'valor' => (int) $rev->conteo_revisado, 'texto' => (string) (int) $rev->conteo_revisado];
            }

            return ['tipo' => 'por_validar', 'valor' => null, 'texto' => 'Por validar'];
        }

        return match ($pase->estado) {
            'procesado' => ['tipo' => 'por_validar', 'valor' => null, 'texto' => 'Por validar'],
            'con_incidencia' => ['tipo' => 'incidencia', 'valor' => null, 'texto' => 'Con incidencia'],
            default => ['tipo' => 'sin_evidencia', 'valor' => null, 'texto' => 'Sin evidencia'],
        };
    }

    // Evita que Excel interprete como formula un texto que empieza con = + - @
    public static function celdaSegura($valor)
    {
        if (is_string($valor) && $valor !== '' && in_array($valor[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $valor;
        }

        return $valor;
    }
}
