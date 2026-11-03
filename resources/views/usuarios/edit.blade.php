@extends('layouts.app')

@section('titulo', 'Editar usuario')

@section('content')
<h1 class="h3 mb-3">Editar usuario</h1>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('usuarios.update', $usuario->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $usuario->nombre) }}">
                @error('nombre')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Correo electrónico</label>
                <input type="email" name="correo" class="form-control @error('correo') is-invalid @enderror" value="{{ old('correo', $usuario->correo) }}">
                @error('correo')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Rol</label>
                    <select name="rol" class="form-select @error('rol') is-invalid @enderror">
                        <option value="administrador" {{ old('rol', $usuario->rol) === 'administrador' ? 'selected' : '' }}>Administrador</option>
                        <option value="encargado" {{ old('rol', $usuario->rol) === 'encargado' ? 'selected' : '' }}>Encargado</option>
                        <option value="profesor" {{ old('rol', $usuario->rol) === 'profesor' ? 'selected' : '' }}>Profesor</option>
                    </select>
                    @error('rol')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-select @error('activo') is-invalid @enderror">
                        <option value="1" {{ (string) old('activo', $usuario->activo ? '1' : '0') === '1' ? 'selected' : '' }}>Activo</option>
                        <option value="0" {{ (string) old('activo', $usuario->activo ? '1' : '0') === '0' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                    @error('activo')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            @if (is_null($usuario->activado_en))
                <div class="alert alert-warning small">
                    Esta cuenta está pendiente de activación: su dueño todavía no elige su contraseña.
                    Si cambias el correo, se enviará un nuevo enlace al correo nuevo y el anterior dejará de servir.
                </div>
            @else
                <p class="text-muted small">
                    Las contraseñas las define cada usuario. Si la olvida, puede recuperarla desde «¿Olvidaste tu contraseña?».
                </p>
            @endif

            <p class="text-muted small">
                Si cambias el correo, el rol o el estado, las sesiones abiertas de este usuario se cerrarán.
            </p>

            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
