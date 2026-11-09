<?php

namespace App\Http\Controllers;

use App\Services\ReporteService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReporteController extends Controller
{
    public function index()
    {
        return view('reportes.index');
    }

    // FN.14 Reporte por clase
    public function clase(Request $request, ReporteService $servicio)
    {
        $filtros = $this->filtros($request);
        $datos = $servicio->porClase(Auth::user(), $filtros);
        $formato = $request->query('formato');

        if ($formato === 'csv') {
            return $this->csv('reporte-por-clase', [
                'Asignatura', 'Grupo', 'Profesor', 'Periodo', 'Sesiones', 'Con asistencia confirmada',
                'Inscritos (sesiones confirmadas)', 'Asistentes', 'Justificados', 'Faltas',
                '% asistencia', '% ausencia total', '% faltas no justificadas',
            ], collect($datos['filas'])->push($datos['total'])->map(fn ($f) => [
                $f['asignatura'], $f['grupo'], $f['profesor'], $f['periodo'], $f['sesiones'], $f['confirmadas'],
                $f['inscritos'], $f['asistentes'], $f['justificados'], $f['faltas'],
                $f['asistencia_pct'] ?? '', $f['ausencia_pct'] ?? '', $f['faltas_pct'] ?? '',
            ])->all());
        }

        $opciones = $servicio->opciones(Auth::user());

        if ($formato === 'pdf') {
            return Pdf::loadView('reportes.pdf.clase', $this->datosPdf($datos, $filtros, $opciones))
                ->setPaper('letter', 'landscape')
                ->download('reporte-por-clase-' . now()->format('Ymd-His') . '.pdf');
        }

        return view('reportes.clase', compact('datos', 'filtros', 'opciones'));
    }

    // FN.15 Reporte de los tres momentos
    public function momentos(Request $request, ReporteService $servicio)
    {
        $filtros = $this->filtros($request);
        $datos = $servicio->momentos(Auth::user(), $filtros);
        $formato = $request->query('formato');

        if ($formato === 'csv') {
            return $this->csv('reporte-tres-momentos', [
                'Fecha', 'Horario', 'Asignatura', 'Grupo', 'Espacio', 'Inscritos',
                'Inicio', 'Desarrollo', 'Cierre', 'Presencia media', 'Ausentismo estimado (%)', 'Estimación',
            ], collect($datos['filas'])->map(fn ($f) => [
                $f['fecha'], $f['horario'], $f['asignatura'], $f['grupo'], $f['espacio'], $f['inscritos'],
                $f['momentos']['inicio']['texto'], $f['momentos']['desarrollo']['texto'], $f['momentos']['cierre']['texto'],
                $f['media'] ?? '', $f['ausentismo'] ?? '',
                ['vigente' => 'Vigente', 'desactualizada' => 'Desactualizada', 'sin' => 'Sin estimación'][$f['estimacion']],
            ])->all());
        }

        $opciones = $servicio->opciones(Auth::user());

        if ($formato === 'pdf') {
            return Pdf::loadView('reportes.pdf.momentos', $this->datosPdf($datos, $filtros, $opciones))
                ->setPaper('letter', 'landscape')
                ->download('reporte-tres-momentos-' . now()->format('Ymd-His') . '.pdf');
        }

        return view('reportes.momentos', compact('datos', 'filtros', 'opciones'));
    }

    // FN.16 Reporte comparativo entre grupos
    public function grupos(Request $request, ReporteService $servicio)
    {
        $filtros = $this->filtros($request);
        $datos = $servicio->porGrupo(Auth::user(), $filtros);
        $formato = $request->query('formato');

        if ($formato === 'csv') {
            return $this->csv('reporte-comparativo-grupos', [
                'Grupo', 'Programa', 'Periodo', 'Clases', 'Sesiones', 'Con asistencia confirmada',
                'Inscritos (sesiones confirmadas)', 'Asistentes', 'Justificados', 'Faltas',
                '% asistencia', '% ausencia total', '% faltas no justificadas',
            ], collect($datos['filas'])->map(fn ($f) => [
                $f['grupo'], $f['programa'], $f['periodo'], $f['clases'], $f['sesiones'], $f['confirmadas'],
                $f['inscritos'], $f['asistentes'], $f['justificados'], $f['faltas'],
                $f['asistencia_pct'] ?? '', $f['ausencia_pct'] ?? '', $f['faltas_pct'] ?? '',
            ])->push([
                'TOTAL', '', '', '', $datos['total']['sesiones'], $datos['total']['confirmadas'],
                $datos['total']['inscritos'], $datos['total']['asistentes'], $datos['total']['justificados'], $datos['total']['faltas'],
                $datos['total']['asistencia_pct'] ?? '', $datos['total']['ausencia_pct'] ?? '', $datos['total']['faltas_pct'] ?? '',
            ])->all());
        }

        $opciones = $servicio->opciones(Auth::user());

        if ($formato === 'pdf') {
            return Pdf::loadView('reportes.pdf.grupos', $this->datosPdf($datos, $filtros, $opciones))
                ->setPaper('letter', 'landscape')
                ->download('reporte-comparativo-grupos-' . now()->format('Ymd-His') . '.pdf');
        }

        return view('reportes.grupos', compact('datos', 'filtros', 'opciones'));
    }

    // FN.17 Reporte de incidencias y pendientes
    public function incidencias(Request $request, ReporteService $servicio)
    {
        $filtros = $this->filtros($request);
        $datos = $servicio->incidencias(Auth::user(), $filtros);
        $formato = $request->query('formato');

        if ($formato === 'csv') {
            return $this->csv('reporte-incidencias-pendientes', [
                'Fecha', 'Asignatura', 'Grupo', 'Espacio', 'Momento', 'Tipo', 'Estado', 'Detalle',
            ], collect($datos['filas'])->map(fn ($f) => [
                $f['fecha'], $f['asignatura'], $f['grupo'], $f['espacio'], $f['momento'], $f['tipo_texto'], $f['estado_texto'], $f['detalle'],
            ])->all());
        }

        $opciones = $servicio->opciones(Auth::user());

        if ($formato === 'pdf') {
            return Pdf::loadView('reportes.pdf.incidencias', $this->datosPdf($datos, $filtros, $opciones))
                ->setPaper('letter', 'landscape')
                ->download('reporte-incidencias-pendientes-' . now()->format('Ymd-His') . '.pdf');
        }

        $tipos = ReporteService::TIPOS;

        return view('reportes.incidencias', compact('datos', 'filtros', 'opciones', 'tipos'));
    }

    // Filtros validados; solo se conservan los que traen valor
    private function filtros(Request $request): array
    {
        $request->validate([
            'periodo' => 'nullable|string|max:20',
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => 'nullable|date_format:Y-m-d|after_or_equal:desde',
            'grupo_id' => 'nullable|integer',
            'espacio_id' => 'nullable|integer',
            'tipo' => 'nullable|in:' . implode(',', array_keys(ReporteService::TIPOS)),
            'formato' => 'nullable|in:csv,pdf',
        ], [
            'desde.date_format' => 'La fecha inicial no es válida',
            'hasta.date_format' => 'La fecha final no es válida',
            'hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial',
        ]);

        return array_filter($request->only(['periodo', 'desde', 'hasta', 'grupo_id', 'espacio_id', 'tipo']), fn ($v) => $v !== null && $v !== '');
    }

    private function datosPdf(array $datos, array $filtros, array $opciones): array
    {
        $grupo = !empty($filtros['grupo_id']) ? $opciones['grupos']->firstWhere('id', (int) $filtros['grupo_id']) : null;
        $espacio = !empty($filtros['espacio_id']) ? $opciones['espacios']->firstWhere('id', (int) $filtros['espacio_id']) : null;

        return [
            'datos' => $datos,
            'filtros' => $filtros,
            'grupoNombre' => $grupo ? $grupo->clave : null,
            'espacioNombre' => $espacio ? $espacio->nombre : null,
            'generado' => now()->timezone(config('app.timezone_local', 'America/Mexico_City'))->format('d/m/Y H:i'),
            'usuario' => Auth::user()->nombre,
        ];
    }

    private function csv(string $nombre, array $encabezados, array $filas)
    {
        return response()->streamDownload(function () use ($encabezados, $filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $encabezados);

            foreach ($filas as $fila) {
                fputcsv($salida, array_map([ReporteService::class, 'celdaSegura'], $fila));
            }

            fclose($salida);
        }, $nombre . '-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
