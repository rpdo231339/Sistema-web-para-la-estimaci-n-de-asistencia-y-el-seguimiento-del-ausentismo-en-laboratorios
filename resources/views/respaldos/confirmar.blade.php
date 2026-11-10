@extends('layouts.app')

@section('titulo', 'Restaurar copia')

@section('content')
@php $zona = config('app.timezone_local', 'America/Mexico_City'); @endphp

<div class="row justify-content-center">
    <div class="col-lg-7">
        <h1 class="h3 mb-3">Restaurar la copia #{{ $respaldo->id }}</h1>

        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">Generada</dt>
                    <dd class="col-7">{{ $respaldo->iniciado_en->timezone($zona)->format('d/m/Y H:i') }}</dd>
                    <dt class="col-5">Tamaño</dt>
                    <dd class="col-7">{{ number_format($respaldo->bytes / 1048576, 2) }} MB</dd>
                    <dt class="col-5">Contenido</dt>
                    <dd class="col-7">
                        {{ is_array($respaldo->manifiesto['tablas'] ?? null) ? array_sum($respaldo->manifiesto['tablas']) : 0 }} filas ·
                        {{ $respaldo->manifiesto['archivos'] ?? 0 }} archivos
                    </dd>
                </dl>
            </div>
        </div>

        @if ($problemas)
            <div class="alert alert-danger">
                <strong>La copia no pasó la verificación de integridad y no se puede restaurar:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($problemas as $problema)
                        <li>{{ $problema }}</li>
                    @endforeach
                </ul>
            </div>

            <a href="{{ route('respaldos.index') }}" class="btn btn-secondary">Volver</a>
        @else
            <div class="alert alert-success small">La copia está íntegra (huella, archivos y base de datos verificados) y es compatible con la estructura actual.</div>

            <div class="alert alert-warning">
                <strong>Antes de continuar:</strong>
                <ul class="mb-0 mt-2">
                    <li>Se reemplazarán <strong>todos los datos y archivos</strong> actuales por los de la copia. Lo capturado después de esa fecha se perderá de la vista del sistema.</li>
                    <li>Primero se genera automáticamente una copia preventiva del estado actual. Si algo falla, se intenta volver a ese estado.</li>
                    <li>Todas las sesiones se cerrarán, incluida la tuya. Conviene que nadie más esté trabajando.</li>
                </ul>
            </div>

            <form action="{{ route('respaldos.restaurar', $respaldo->id) }}" method="POST" class="card shadow-sm">
                @csrf
                <div class="card-body">
                    <div class="mb-3">
                        <label for="confirmacion" class="form-label">Escribe <strong>RESTAURAR</strong> para confirmar</label>
                        <input type="text" id="confirmacion" name="confirmacion" class="form-control @error('confirmacion') is-invalid @enderror" autocomplete="off" required>
                        @error('confirmacion')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Tu contraseña</label>
                        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger">Restaurar</button>
                        <a href="{{ route('respaldos.index') }}" class="btn btn-secondary">Cancelar</a>
                    </div>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
