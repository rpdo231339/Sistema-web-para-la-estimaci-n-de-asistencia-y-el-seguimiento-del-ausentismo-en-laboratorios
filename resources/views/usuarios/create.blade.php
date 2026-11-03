@extends('layouts.app')

@section('titulo', 'Nuevo usuario')

@section('content')
<h1 class="h3 mb-3">Registrar usuario</h1>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('usuarios.store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre') }}">
                @error('nombre')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Correo electrónico</label>
                <input type="email" name="correo" class="form-control @error('correo') is-invalid @enderror" value="{{ old('correo') }}">
                @error('correo')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Rol</label>
                    <select name="rol" class="form-select @error('rol') is-invalid @enderror">
                        <option value="">Selecciona un rol</option>
                        <option value="administrador" {{ old('rol') === 'administrador' ? 'selected' : '' }}>Administrador</option>
                        <option value="encargado" {{ old('rol') === 'encargado' ? 'selected' : '' }}>Encargado</option>
                        <option value="profesor" {{ old('rol') === 'profesor' ? 'selected' : '' }}>Profesor</option>
                    </select>
                    @error('rol')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-select @error('activo') is-invalid @enderror">
                        <option value="1" {{ (string) old('activo', '1') === '1' ? 'selected' : '' }}>Activo</option>
                        <option value="0" {{ (string) old('activo', '1') === '0' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                    @error('activo')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="alert alert-info small">
                <i class="fa-solid fa-envelope"></i>
                No se asigna ninguna contraseña. Al guardar, el usuario recibe un correo con un enlace de un solo uso para elegir la suya.
                La cuenta queda como «pendiente de activación» hasta que lo use.
            </div>

            <button type="submit" class="btn btn-success">Guardar y enviar activación</button>
            <a href="{{ route('usuarios.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
