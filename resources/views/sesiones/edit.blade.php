@extends('layouts.app')

@section('titulo', 'Editar sesión')

@section('content')
<h1 class="h3 mb-3">Editar sesión</h1>

<div class="card shadow-sm">
    <div class="card-body">

        <p class="mb-3">
            <strong>{{ data_get($sesion->contexto_historico, 'asignatura', '—') }}</strong>
            · {{ data_get($sesion->contexto_historico, 'grupo.clave', '—') }}
            · {{ data_get($sesion->contexto_historico, 'espacio.nombre', '—') }}
            · {{ substr($sesion->hora_inicio, 0, 5) }}–{{ substr($sesion->hora_fin, 0, 5) }}
        </p>

        <form action="{{ route('sesiones.update', $sesion->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label">Fecha</label>
                <input type="date" name="fecha" class="form-control @error('fecha') is-invalid @enderror" value="{{ old('fecha', $sesion->fecha->format('Y-m-d')) }}" {{ $fechaBloqueada ? 'readonly' : '' }}>
                @error('fecha')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror

                @if ($fechaBloqueada)
                    <div class="form-text">La fecha no se puede cambiar porque la sesión ya se realizó o tiene asistencia registrada. Si fue un error, cancélala y crea otra.</div>
                @else
                    <div class="form-text">Debe caer en el día de la semana del bloque.</div>
                @endif
            </div>

            <div class="mb-3">
                <label class="form-label">Observaciones</label>
                <textarea name="observaciones" rows="3" class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones', $sesion->observaciones) }}</textarea>
                @error('observaciones')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <p class="text-muted small">El grupo, el espacio, el horario y los inscritos de la sesión son una copia histórica y no se editan aquí.</p>

            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="{{ route('sesiones.show', $sesion->id) }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
