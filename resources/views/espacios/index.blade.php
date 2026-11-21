@extends('layouts.app')

@section('titulo', 'Espacios')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Aulas y laboratorios</h1>

    <a href="{{ route('espacios.create') }}" class="btn btn-success">
        <i class="fa-solid fa-plus"></i> Nuevo espacio
    </a>
</div>

<form action="{{ route('espacios.index') }}" method="GET" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">Buscar</label>
            <input type="text" name="buscar" class="form-control" placeholder="Nombre o ubicación" value="{{ request('buscar') }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select">
                <option value="">Todos</option>
                <option value="aula" {{ request('tipo') === 'aula' ? 'selected' : '' }}>Aula</option>
                <option value="laboratorio" {{ request('tipo') === 'laboratorio' ? 'selected' : '' }}>Laboratorio</option>
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
            <a href="{{ route('espacios.index') }}" class="btn btn-secondary">Limpiar</a>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Ubicación</th>
                    <th>Encargados</th>
                    <th>Bloques de clase</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($espacios as $espacio)
                    <tr>
                        <td>{{ $espacio->id }}</td>
                        <td>{{ $espacio->nombre }}</td>
                        <td>{{ ucfirst($espacio->tipo) }}</td>
                        <td>{{ $espacio->ubicacion }}</td>
                        <td>
                            @forelse ($espacio->encargadosActivos as $encargado)
                                {{ $encargado->nombre }}@if (!$loop->last), @endif
                            @empty
                                <span class="text-muted">Sin asignar</span>
                            @endforelse
                        </td>
                        <td>{{ $espacio->bloques_vigentes }}</td>
                        <td>
                            @if ($espacio->activo)
                                <span class="badge text-bg-success">Activo</span>
                            @else
                                <span class="badge text-bg-secondary">Inactivo</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('espacios.edit', $espacio->id) }}" class="btn btn-warning btn-sm">Editar</a>

                            @if ($espacio->activo)
                                <form action="{{ route('espacios.estado', $espacio->id) }}" method="POST" style="display:inline;" class="form-confirmar" data-mensaje="¿Desactivar {{ $espacio->nombre }}?@if ($espacio->bloques_vigentes > 0) Lo usan {{ $espacio->bloques_vigentes }} bloque(s) de clases vigentes: conservarán su horario, pero el espacio no podrá elegirse en bloques nuevos.@else Dejará de estar disponible para nuevas clases.@endif">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-danger btn-sm">Desactivar</button>
                                </form>
                            @else
                                <form action="{{ route('espacios.estado', $espacio->id) }}" method="POST" style="display:inline;" class="form-confirmar" data-mensaje="¿Reactivar {{ $espacio->nombre }}?">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-success btn-sm">Reactivar</button>
                                </form>
                            @endif

                            @if ($eliminables->contains($espacio->id))
                                <form action="{{ route('espacios.eliminar', $espacio->id) }}" method="POST" style="display:inline;" class="form-confirmar" data-mensaje="¿Eliminar definitivamente {{ $espacio->nombre }}? Esta acción no se puede deshacer.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="No se ha usado en ningún bloque ni sesión: se puede eliminar por completo"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            @if (Auth::user()->rol === 'encargado')
                                No tienes espacios asignados. Pide al administrador que te asigne uno o registra uno nuevo.
                            @else
                                No se encontraron espacios
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3">
    <span class="text-muted small">{{ $espacios->total() }} espacio(s) en total</span>
    {{ $espacios->links() }}
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
