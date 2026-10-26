@extends('layouts.app')

@section('titulo', 'Pases de lista')

@section('content')
@php
    $etiquetasEstado = [
        'sin_evidencia' => ['Sin evidencia', 'secondary'],
        'procesado' => ['Procesado', 'info'],
        'validado' => ['Validado', 'success'],
        'con_incidencia' => ['Con incidencia', 'danger'],
    ];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Pases de lista</h1>
</div>

@if ($espacios->isEmpty())
    <div class="alert alert-info">
        No tienes espacios asignados. Pide al administrador que te asigne uno para ver las sesiones que se realizan en él.
    </div>
@endif

<form action="{{ route('pases.index') }}" method="GET" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Asignatura o profesor</label>
            <input type="text" name="buscar" class="form-control" value="{{ request('buscar') }}">
        </div>

        <div class="col-md-3">
            <label class="form-label">Espacio</label>
            <select name="espacio_id" class="form-select">
                <option value="">Todos mis espacios</option>
                @foreach ($espacios as $espacio)
                    <option value="{{ $espacio->id }}" {{ (string) request('espacio_id') === (string) $espacio->id ? 'selected' : '' }}>{{ $espacio->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" class="form-control" value="{{ request('desde') }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" class="form-control" value="{{ request('hasta') }}">
        </div>

        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="{{ route('pases.index') }}" class="btn btn-secondary">Limpiar</a>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Asignatura</th>
                    <th>Profesor</th>
                    <th>Espacio</th>
                    <th>Horario</th>
                    <th>Momentos</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sesiones as $sesion)
                    <tr>
                        <td>{{ $sesion->fecha->format('d/m/Y') }}</td>
                        <td>{{ data_get($sesion->contexto_historico, 'asignatura', '—') }}</td>
                        <td>{{ data_get($sesion->contexto_historico, 'profesor', $sesion->profesor->nombre ?? '—') }}</td>
                        <td>{{ data_get($sesion->contexto_historico, 'espacio.nombre', '—') }}</td>
                        <td>{{ substr($sesion->hora_inicio, 0, 5) }}–{{ substr($sesion->hora_fin, 0, 5) }}</td>
                        <td>
                            @forelse ($sesion->pases as $pase)
                                <span class="badge text-bg-{{ $etiquetasEstado[$pase->estado][1] }} me-1" title="{{ $etiquetasEstado[$pase->estado][0] }}">
                                    @if ($pase->evidenciaVigente)
                                        <i class="fa-solid fa-image"></i>
                                    @endif
                                    {{ \App\Models\PaseLista::MOMENTOS[$pase->momento] }}
                                </span>
                            @empty
                                <span class="text-muted small">El profesor aún no define los momentos</span>
                            @endforelse
                        </td>
                        <td>
                            <a href="{{ route('pases.show', $sesion->id) }}" class="btn btn-primary btn-sm">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">No hay sesiones en tus espacios con esos filtros</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3">
    <span class="text-muted small">{{ $sesiones->total() }} sesión(es)</span>
    {{ $sesiones->links() }}
</div>

<p class="text-muted small mt-3 mb-0">El ícono de imagen indica que el momento ya tiene un archivo cargado. El color es el estado del pase de lista.</p>
@endsection
