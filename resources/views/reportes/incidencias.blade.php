@extends('layouts.app')

@section('titulo', 'Incidencias y pendientes')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Incidencias y pendientes</h1>
    <a href="{{ route('reportes.index') }}" class="btn btn-secondary">Volver</a>
</div>

@include('reportes._filtros', ['ruta' => 'reportes.incidencias'])

@if ($datos['truncado'])
    <div class="alert alert-warning small">Se revisaron solo las primeras {{ $datos['limite'] }} sesiones. Acota el periodo o las fechas para ver el resto.</div>
@endif

<div class="row g-2 mb-3">
    @foreach ($tipos as $clave => $texto)
        <div class="col-6 col-md-2">
            <div class="card shadow-sm text-center h-100">
                <div class="card-body py-2">
                    <div class="fs-4 fw-semibold">{{ $datos['resumen'][$clave] }}</div>
                    <div class="small text-muted">{{ $texto }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Asignatura</th>
                    <th>Grupo</th>
                    <th>Espacio</th>
                    <th>Momento</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Detalle</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($datos['filas'] as $f)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($f['fecha'])->format('d/m/Y') }}</td>
                        <td>{{ $f['asignatura'] }}</td>
                        <td>{{ $f['grupo'] }}</td>
                        <td>{{ $f['espacio'] }}</td>
                        <td>{{ $f['momento'] }}</td>
                        <td>{{ $f['tipo_texto'] }}</td>
                        <td>
                            <span class="badge text-bg-{{ $f['estado'] === 'pendiente' ? 'warning' : 'danger' }}">{{ $f['estado_texto'] }}</span>
                        </td>
                        <td class="small">{{ $f['detalle'] }}</td>
                        <td>
                            @if (Auth::user()->rol === 'profesor')
                                <a href="{{ route('sesiones.show', $f['sesion_id']) }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                            @elseif (Auth::user()->rol === 'encargado')
                                <a href="{{ route('pases.show', $f['sesion_id']) }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">No hay incidencias ni pendientes para estos filtros</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<p class="small text-muted mt-3">
    <strong>Pendiente</strong> es algo que falta hacer (por ejemplo, cargar una imagen o validar un conteo); <strong>incidencia</strong> es un problema ya registrado.
    Un momento sin evidencia solo se marca cuando su hora ya pasó, y una asistencia solo cuando la fecha de la sesión ya pasó.
</p>
@endsection
