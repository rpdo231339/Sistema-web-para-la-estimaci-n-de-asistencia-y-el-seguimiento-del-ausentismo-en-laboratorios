@extends('layouts.app')

@section('titulo', 'Grupos')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Grupos académicos</h1>

    <a href="{{ route('grupos.create') }}" class="btn btn-success">
        <i class="fa-solid fa-plus"></i> Nuevo grupo
    </a>
</div>

<form action="{{ route('grupos.index') }}" method="GET" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">Buscar</label>
            <input type="text" name="buscar" class="form-control" placeholder="Clave o programa" value="{{ request('buscar') }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Periodo</label>
            <select name="periodo" class="form-select">
                <option value="">Todos</option>
                @foreach ($periodos as $periodo)
                    <option value="{{ $periodo }}" {{ request('periodo') === $periodo ? 'selected' : '' }}>{{ $periodo }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
                <option value="">Todos</option>
                <option value="activo" {{ request('estado') === 'activo' ? 'selected' : '' }}>Activos</option>
                <option value="inactivo" {{ request('estado') === 'inactivo' ? 'selected' : '' }}>Inactivos</option>
            </select>
        </div>

        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="{{ route('grupos.index') }}" class="btn btn-secondary">Limpiar</a>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Clave</th>
                    <th>Programa</th>
                    <th>Cuatrimestre</th>
                    <th>Periodo</th>
                    <th>Clases vigentes</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($grupos as $grupo)
                    <tr>
                        <td>{{ $grupo->id }}</td>
                        <td>{{ $grupo->clave }}</td>
                        <td>{{ $grupo->programa }}</td>
                        <td>{{ $grupo->cuatrimestre }}</td>
                        <td>{{ $grupo->periodo }}</td>
                        <td>{{ $grupo->clases_vigentes }}</td>
                        <td>
                            @if ($grupo->activo)
                                <span class="badge text-bg-success">Activo</span>
                            @else
                                <span class="badge text-bg-secondary">Inactivo</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('grupos.edit', $grupo->id) }}" class="btn btn-warning btn-sm">Editar</a>

                            @if ($grupo->activo)
                                <form action="{{ route('grupos.estado', $grupo->id) }}" method="POST" style="display:inline;" class="form-confirmar" data-mensaje="¿Desactivar el grupo {{ $grupo->clave }} ({{ $grupo->periodo }})?@if ($grupo->clases_vigentes > 0) Tiene {{ $grupo->clases_vigentes }} clase(s) vigente(s): conservarán su vínculo, pero el grupo no podrá elegirse en clases nuevas.@else Ya no podrá elegirse en clases nuevas.@endif">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-danger btn-sm">Desactivar</button>
                                </form>
                            @else
                                <form action="{{ route('grupos.estado', $grupo->id) }}" method="POST" style="display:inline;" class="form-confirmar" data-mensaje="¿Reactivar el grupo {{ $grupo->clave }} ({{ $grupo->periodo }})?">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-success btn-sm">Reactivar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No se encontraron grupos</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3">
    <span class="text-muted small">{{ $grupos->total() }} grupo(s) en total</span>
    {{ $grupos->links() }}
</div>

<script>
    // Confirmacion antes de desactivar o reactivar
    document.querySelectorAll('.form-confirmar').forEach(function (formulario) {
        formulario.addEventListener('submit', function (evento) {
            if (!confirm(formulario.dataset.mensaje)) {
                evento.preventDefault();
            }
        });
    });
</script>
@endsection
