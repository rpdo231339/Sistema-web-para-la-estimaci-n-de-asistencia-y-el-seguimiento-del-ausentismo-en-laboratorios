@extends('layouts.app')

@section('titulo', 'Editar grupo')

@section('content')
<h1 class="h3 mb-3">Editar grupo</h1>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('grupos.update', $grupo->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Clave</label>
                    <input type="text" name="clave" class="form-control @error('clave') is-invalid @enderror" value="{{ old('clave', $grupo->clave) }}">
                    @error('clave')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Periodo</label>
                    <input type="text" name="periodo" class="form-control @error('periodo') is-invalid @enderror" value="{{ old('periodo', $grupo->periodo) }}">
                    @error('periodo')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Programa académico</label>
                <input type="text" name="programa" class="form-control @error('programa') is-invalid @enderror" value="{{ old('programa', $grupo->programa) }}">
                @error('programa')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Cuatrimestre</label>
                    <input type="number" name="cuatrimestre" min="1" class="form-control @error('cuatrimestre') is-invalid @enderror" value="{{ old('cuatrimestre', $grupo->cuatrimestre) }}">
                    @error('cuatrimestre')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Estado</label>
                    <select name="activo" class="form-select @error('activo') is-invalid @enderror">
                        <option value="1" {{ (string) old('activo', $grupo->activo ? '1' : '0') === '1' ? 'selected' : '' }}>Activo</option>
                        <option value="0" {{ (string) old('activo', $grupo->activo ? '1' : '0') === '0' ? 'selected' : '' }}>Inactivo</option>
                    </select>
                    @error('activo')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="{{ route('grupos.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
