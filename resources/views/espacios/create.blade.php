@extends('layouts.app')

@section('titulo', 'Nuevo espacio')

@section('content')
<h1 class="h3 mb-3">Registrar espacio</h1>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('espacios.store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label class="form-label">Nombre</label>
                <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre') }}">
                @error('nombre')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-select @error('tipo') is-invalid @enderror">
                        <option value="">Selecciona un tipo</option>
                        <option value="aula" {{ old('tipo') === 'aula' ? 'selected' : '' }}>Aula</option>
                        <option value="laboratorio" {{ old('tipo') === 'laboratorio' ? 'selected' : '' }}>Laboratorio</option>
                    </select>
                    @error('tipo')
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

            <div class="mb-3">
                <label class="form-label">Ubicación</label>
                <input type="text" name="ubicacion" class="form-control @error('ubicacion') is-invalid @enderror" value="{{ old('ubicacion') }}" placeholder="Edificio, piso o referencia">
                @error('ubicacion')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Descripción (opcional)</label>
                <textarea name="descripcion" rows="3" class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion') }}</textarea>
                @error('descripcion')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            @if (Auth::user()->rol === 'encargado')
                <p class="text-muted small">Al registrarlo quedará asignado a tu cuenta.</p>
            @else
                <p class="text-muted small">Después de guardarlo podrás asignarle encargados desde la opción Editar.</p>
            @endif

            <button type="submit" class="btn btn-success">Guardar</button>
            <a href="{{ route('espacios.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
