@extends('layouts.app')

@section('titulo', 'Usuarios')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Usuarios del sistema</h1>

    <a href="{{ route('usuarios.create') }}" class="btn btn-success">
        <i class="fa-solid fa-plus"></i> Nuevo usuario
    </a>
</div>

<form action="{{ route('usuarios.index') }}" method="GET" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">Buscar</label>
            <input type="text" name="buscar" class="form-control" placeholder="Nombre o correo" value="{{ request('buscar') }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Rol</label>
            <select name="rol" class="form-select">
                <option value="">Todos</option>
                <option value="administrador" {{ request('rol') === 'administrador' ? 'selected' : '' }}>Administrador</option>
                <option value="encargado" {{ request('rol') === 'encargado' ? 'selected' : '' }}>Encargado</option>
                <option value="profesor" {{ request('rol') === 'profesor' ? 'selected' : '' }}>Profesor</option>
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
            <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">Limpiar</a>
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
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($usuarios as $usuario)
                    <tr>
                        <td>{{ $usuario->id }}</td>
                        <td>{{ $usuario->nombre }}</td>
                        <td>{{ $usuario->correo }}</td>
                        <td>{{ ucfirst($usuario->rol) }}</td>
                        <td>
                            @if ($usuario->activo)
                                <span class="badge text-bg-success">Activo</span>
                            @else
                                <span class="badge text-bg-secondary">Inactivo</span>
                            @endif

                            @if (is_null($usuario->activado_en))
                                <span class="badge text-bg-warning">Pendiente de activación</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('usuarios.edit', $usuario->id) }}" class="btn btn-warning btn-sm">Editar</a>

                            @if (is_null($usuario->activado_en) && $usuario->activo)
                                <form action="{{ route('usuarios.activacion', $usuario->id) }}" method="POST" style="display:inline;" class="form-confirmar" data-mensaje="¿Enviar un nuevo enlace de activación a {{ $usuario->correo }}? El anterior dejará de servir.">
                                    @csrf
                                    <button type="submit" class="btn btn-info btn-sm">Reenviar activación</button>
                                </form>
                            @endif

                            @if ($usuario->activo)
                                <form action="{{ route('usuarios.estado', $usuario->id) }}" method="POST" style="display:inline;" class="form-confirmar" data-mensaje="¿Desactivar a {{ $usuario->nombre }}? Perderá el acceso al sistema.">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-danger btn-sm">Desactivar</button>
                                </form>
                            @else
                                <form action="{{ route('usuarios.estado', $usuario->id) }}" method="POST" style="display:inline;" class="form-confirmar" data-mensaje="¿Reactivar a {{ $usuario->nombre }}?">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-success btn-sm">Reactivar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">No se encontraron usuarios</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3">
    <span class="text-muted small">{{ $usuarios->total() }} usuario(s) en total</span>
    {{ $usuarios->links() }}
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
