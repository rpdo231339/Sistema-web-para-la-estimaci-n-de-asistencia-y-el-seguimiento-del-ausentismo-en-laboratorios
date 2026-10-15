@extends('layouts.app')

@section('titulo', 'Mis sesiones')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Mis sesiones</h1>

    <a href="{{ route('sesiones.create') }}" class="btn btn-success">
        <i class="fa-solid fa-plus"></i> Nueva sesión
    </a>
</div>

<form action="{{ route('sesiones.index') }}" method="GET" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Asignatura</label>
            <input type="text" name="buscar" class="form-control" value="{{ request('buscar') }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" class="form-control" value="{{ request('desde') }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" class="form-control" value="{{ request('hasta') }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
                <option value="">Todos</option>
                <option value="programada" {{ request('estado') === 'programada' ? 'selected' : '' }}>Programada</option>
                <option value="realizada" {{ request('estado') === 'realizada' ? 'selected' : '' }}>Realizada</option>
                <option value="cancelada" {{ request('estado') === 'cancelada' ? 'selected' : '' }}>Cancelada</option>
            </select>
        </div>

        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="{{ route('sesiones.index') }}" class="btn btn-secondary">Limpiar</a>
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
                    <th>Grupo</th>
                    <th>Horario</th>
                    <th>Espacio</th>
                    <th>Inscritos</th>
                    <th>Sesión</th>
                    <th>Asistencia</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sesiones as $sesion)
                    <tr>
                        <td>{{ $sesion->fecha->format('d/m/Y') }}</td>
                        <td>{{ data_get($sesion->contexto_historico, 'asignatura', '—') }}</td>
                        <td>{{ data_get($sesion->contexto_historico, 'grupo.clave', '—') }} <span class="text-muted small">{{ $sesion->periodo }}</span></td>
                        <td>{{ substr($sesion->hora_inicio, 0, 5) }}–{{ substr($sesion->hora_fin, 0, 5) }}</td>
                        <td>{{ data_get($sesion->contexto_historico, 'espacio.nombre', '—') }}</td>
                        <td>{{ $sesion->inscritos }}</td>
                        <td>
                            @if ($sesion->estado === 'programada')
                                <span class="badge text-bg-primary">Programada</span>
                            @elseif ($sesion->estado === 'realizada')
                                <span class="badge text-bg-success">Realizada</span>
                            @else
                                <span class="badge text-bg-secondary">Cancelada</span>
                            @endif
                        </td>
                        <td>
                            @if (!$sesion->asistencia)
                                <span class="badge text-bg-light border">Sin registro</span>
                            @elseif ($sesion->asistencia->estado === 'borrador')
                                <span class="badge text-bg-warning">Borrador</span>
                            @elseif ($sesion->asistencia->estado === 'pendiente_revision')
                                <span class="badge text-bg-info">Pendiente de revisión</span>
                            @else
                                <span class="badge text-bg-success">Confirmado</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('sesiones.show', $sesion->id) }}" class="btn btn-primary btn-sm">Abrir</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Todavía no tienes sesiones registradas</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3">
    <span class="text-muted small">{{ $sesiones->total() }} sesión(es) en total</span>
    {{ $sesiones->links() }}
</div>
@endsection
