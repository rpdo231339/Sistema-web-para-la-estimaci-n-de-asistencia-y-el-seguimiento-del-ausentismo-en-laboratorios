@extends('layouts.app')

@section('titulo', 'Reporte por clase')

@section('content')
@php $pct = fn ($v) => $v === null ? '—' : $v . ' %'; @endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Reporte por clase</h1>
    <a href="{{ route('reportes.index') }}" class="btn btn-secondary">Volver</a>
</div>

@include('reportes._filtros', ['ruta' => 'reportes.clase'])

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Asignatura</th>
                    <th>Grupo</th>
                    <th>Profesor</th>
                    <th class="text-end">Sesiones</th>
                    <th class="text-end">Confirmadas</th>
                    <th class="text-end">Inscritos</th>
                    <th class="text-end">Asistentes</th>
                    <th class="text-end">Justificados</th>
                    <th class="text-end">Faltas</th>
                    <th class="text-end">% asistencia</th>
                    <th class="text-end">% ausencia</th>
                    <th class="text-end">% faltas</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($datos['filas'] as $f)
                    <tr>
                        <td>{{ $f['asignatura'] }}</td>
                        <td>{{ $f['grupo'] }}</td>
                        <td>{{ $f['profesor'] }}</td>
                        <td class="text-end">{{ $f['sesiones'] }}</td>
                        <td class="text-end">{{ $f['confirmadas'] }}</td>
                        <td class="text-end">{{ $f['inscritos'] }}</td>
                        <td class="text-end">{{ $f['asistentes'] }}</td>
                        <td class="text-end">{{ $f['justificados'] }}</td>
                        <td class="text-end">{{ $f['faltas'] }}</td>
                        <td class="text-end">{{ $pct($f['asistencia_pct']) }}</td>
                        <td class="text-end">{{ $pct($f['ausencia_pct']) }}</td>
                        <td class="text-end">{{ $pct($f['faltas_pct']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="text-center text-muted py-4">No hay sesiones para estos filtros</td>
                    </tr>
                @endforelse
            </tbody>
            @if (count($datos['filas']) > 0)
                @php $t = $datos['total']; @endphp
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="3">TOTAL</td>
                        <td class="text-end">{{ $t['sesiones'] }}</td>
                        <td class="text-end">{{ $t['confirmadas'] }}</td>
                        <td class="text-end">{{ $t['inscritos'] }}</td>
                        <td class="text-end">{{ $t['asistentes'] }}</td>
                        <td class="text-end">{{ $t['justificados'] }}</td>
                        <td class="text-end">{{ $t['faltas'] }}</td>
                        <td class="text-end">{{ $pct($t['asistencia_pct']) }}</td>
                        <td class="text-end">{{ $pct($t['ausencia_pct']) }}</td>
                        <td class="text-end">{{ $pct($t['faltas_pct']) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

<p class="small text-muted mt-3">
    Solo cuentan las sesiones con asistencia <strong>confirmada</strong> por el profesor; las canceladas no se incluyen.
    Ausencia = justificados + faltas. Los porcentajes se calculan sobre la suma de inscritos de las sesiones confirmadas,
    no promediando porcentajes de clases de distinto tamaño.
</p>
@endsection
