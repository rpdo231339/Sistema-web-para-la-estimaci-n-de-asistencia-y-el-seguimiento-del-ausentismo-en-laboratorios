@extends('layouts.app')

@section('titulo', 'Nueva evaluación')

@section('content')
<h1 class="h3 mb-3">Nueva evaluación del conteo</h1>

<div class="card shadow-sm">
    <div class="card-body">
        @error('analisis')
            <div class="alert alert-danger small">{{ $message }}</div>
        @enderror

        <form action="{{ route('evaluaciones.store') }}" method="POST" enctype="multipart/form-data" data-cargando="Registrando…">
            @csrf
            <input type="hidden" name="clave_operacion" value="{{ hash('sha256', (string) \Illuminate\Support\Str::uuid()) }}">

            <div class="mb-3">
                <label class="form-label">Imagen de prueba (JPG o PNG)</label>
                <input type="file" name="archivo" accept="image/jpeg,image/png" class="form-control @error('archivo') is-invalid @enderror">
                @error('archivo')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Ambiente</label>
                    <select name="ambiente" class="form-select @error('ambiente') is-invalid @enderror">
                        <option value="">Selecciona</option>
                        <option value="aula" {{ old('ambiente') === 'aula' ? 'selected' : '' }}>Aula</option>
                        <option value="laboratorio" {{ old('ambiente') === 'laboratorio' ? 'selected' : '' }}>Laboratorio</option>
                    </select>
                    @error('ambiente')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Oclusión observada</label>
                    <select name="oclusion" class="form-select @error('oclusion') is-invalid @enderror">
                        <option value="">Selecciona</option>
                        <option value="nula" {{ old('oclusion') === 'nula' ? 'selected' : '' }}>Nula (todos se ven completos)</option>
                        <option value="parcial" {{ old('oclusion') === 'parcial' ? 'selected' : '' }}>Parcial (algunos tapados)</option>
                        <option value="alta" {{ old('oclusion') === 'alta' ? 'selected' : '' }}>Alta (muchos tapados)</option>
                    </select>
                    @error('oclusion')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Iluminación</label>
                    <input type="text" name="iluminacion" class="form-control @error('iluminacion') is-invalid @enderror" value="{{ old('iluminacion') }}" placeholder="Ej. luz natural y focos encendidos">
                    @error('iluminacion')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Ángulo de captura</label>
                    <input type="text" name="angulo" class="form-control @error('angulo') is-invalid @enderror" value="{{ old('angulo') }}" placeholder="Ej. frontal, esquina superior, a 3 m">
                    @error('angulo')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Personas visibles que cuentas tú (referencia manual)</label>
                <input type="number" min="0" max="1000" step="1" name="referencia_manual" class="form-control @error('referencia_manual') is-invalid @enderror" value="{{ old('referencia_manual') }}">
                <div class="form-text">Cuéntalas antes de ver el resultado automático, para no influir en tu referencia.</div>
                @error('referencia_manual')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="mb-3">
                <label class="form-label">Observaciones (opcional)</label>
                <textarea name="observaciones" rows="2" class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones') }}</textarea>
                @error('observaciones')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit" class="btn btn-success">Registrar y analizar</button>
            <a href="{{ route('evaluaciones.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
