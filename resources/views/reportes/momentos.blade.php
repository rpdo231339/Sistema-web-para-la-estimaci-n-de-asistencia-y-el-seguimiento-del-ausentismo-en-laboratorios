@extends('layouts.app')

@section('titulo', 'Reporte de los tres momentos')

@section('content')
@php
    $clases = ['validado' => '', 'sin_evidencia' => 'text-muted fst-italic', 'por_validar' => 'text-warning-emphasis', 'incidencia' => 'text-danger', 'sin_programar' => 'text-muted fst-italic'];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Reporte de los tres momentos</h1>
    <a href="{{ route('reportes.index') }}" class="btn btn-secondary">Volver</a>
</div>

@include('reportes._filtros', ['ruta' => 'reportes.momentos'])

@if ($datos['truncado'])
    <div class="alert alert-warning small">Se muestran solo las primeras {{ $datos['limite'] }} sesiones. Acota el periodo o las fechas para ver el resto.</div>
@endif

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Asignatura</th>
                    <th>Grupo</th>
                    <th>Espacio</th>
                    <th class="text-end">Inscritos</th>
                    <th class="text-end">Inicio</th>
                    <th class="text-end">Desarrollo</th>
                    <th class="text-end">Cierre</th>
                    <th class="text-end">Media</th>
                    <th class="text-end">Ausentismo est.</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($datos['filas'] as $f)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($f['fecha'])->format('d/m/Y') }}<span class="d-block small text-muted">{{ $f['horario'] }}</span></td>
                        <td>{{ $f['asignatura'] }}</td>
                        <td>{{ $f['grupo'] }}</td>
                        <td>{{ $f['espacio'] }}</td>
                        <td class="text-end">{{ $f['inscritos'] }}</td>
                        @foreach (['inicio', 'desarrollo', 'cierre'] as $m)
                            <td class="text-end {{ $clases[$f['momentos'][$m]['tipo']] }}">{{ $f['momentos'][$m]['texto'] }}</td>
                        @endforeach
                        <td class="text-end">{{ $f['media'] ?? '—' }}</td>
                        <td class="text-end">
                            @if ($f['estimacion'] === 'vigente')
                                {{ $f['ausentismo'] }} %
                            @elseif ($f['estimacion'] === 'desactualizada')
                                <span class="text-warning-emphasis small">Desactualizada</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">No hay sesiones para estos filtros</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<p class="small text-muted mt-3">
    {{ $datos['sesiones'] }} sesión(es), {{ $datos['completas'] }} con los tres momentos validados.
    «Sin evidencia» significa que no hay imagen para ese momento; <strong>no es un conteo de cero</strong>.
    La media y el ausentismo estimado aparecen solo cuando existe una estimación vigente con los tres conteos validados.
</p>
@endsection
