@extends('layouts.app')

@section('titulo', 'Comparativo entre grupos')

@section('content')
@php $pct = fn ($v) => $v === null ? '—' : $v . ' %'; @endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Comparativo entre grupos</h1>
    <a href="{{ route('reportes.index') }}" class="btn btn-secondary">Volver</a>
</div>

@include('reportes._filtros', ['ruta' => 'reportes.grupos'])

@if (count($datos['filas']) > 0)
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h5">Ausencia por grupo y periodo</h2>
            <p class="text-muted small">Ausencia = justificados + faltas, sobre los inscritos de las sesiones confirmadas.</p>

            @foreach ($datos['filas'] as $f)
                <div class="row align-items-center mb-2">
                    <div class="col-md-3 small">{{ $f['grupo'] }} <span class="text-muted">· {{ $f['periodo'] }}</span></div>
                    <div class="col-md-7">
                        <div class="progress" style="height: 18px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: {{ $f['ausencia_pct'] ?? 0 }}%;"></div>
                        </div>
                    </div>
                    <div class="col-md-2 small text-end">{{ $pct($f['ausencia_pct']) }}</div>
                </div>
            @endforeach

            <p class="small text-muted mb-0">La escala va de 0 % a 100 %.</p>
        </div>
    </div>
@endif

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Grupo</th>
                    <th>Programa</th>
                    <th>Periodo</th>
                    <th class="text-end">Clases</th>
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
                        <td>{{ $f['grupo'] }}</td>
                        <td>{{ $f['programa'] }}</td>
                        <td>{{ $f['periodo'] }}</td>
                        <td class="text-end">{{ $f['clases'] }}</td>
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
                        <td colspan="13" class="text-center text-muted py-4">No hay sesiones para estos filtros</td>
                    </tr>
                @endforelse
            </tbody>
            @if (count($datos['filas']) > 0)
                @php $t = $datos['total']; @endphp
                <tfoot>
                    <tr class="fw-semibold">
                        <td colspan="4">TOTAL</td>
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
    Solo cuentan las sesiones con asistencia <strong>confirmada</strong>; las canceladas no se incluyen.
    Cada porcentaje se calcula sumando las cantidades de ausencia y de inscritos de las sesiones incluidas,
    por eso un grupo grande pesa más que uno pequeño; no se promedian porcentajes.
</p>
@endsection
