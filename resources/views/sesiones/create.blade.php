@extends('layouts.app')

@section('titulo', 'Nueva sesión')

@section('content')
<h1 class="h3 mb-3">Registrar sesión</h1>

<div class="card shadow-sm">
    <div class="card-body">

        @if ($bloques->isEmpty())
            <div class="alert alert-info mb-3">
                No tienes bloques disponibles. Registra una clase con su horario, o revisa que su grupo y su espacio sigan activos.
            </div>

            <a href="{{ route('clases.index') }}" class="btn btn-primary">Ir a mis clases</a>
            <a href="{{ route('sesiones.index') }}" class="btn btn-secondary">Cancelar</a>
        @else
            <form action="{{ route('sesiones.store') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label class="form-label">Clase y bloque</label>
                    <select name="horario_clase_id" class="form-select @error('horario_clase_id') is-invalid @enderror">
                        <option value="">Selecciona el bloque</option>
                        @foreach ($bloques as $bloque)
                            <option value="{{ $bloque->id }}" data-inicio="{{ substr($bloque->hora_inicio, 0, 5) }}" data-fin="{{ substr($bloque->hora_fin, 0, 5) }}" {{ (string) old('horario_clase_id') === (string) $bloque->id ? 'selected' : '' }}>
                                {{ $bloque->clase->asignatura }} · {{ $bloque->clase->grupo->clave }} ({{ $bloque->clase->grupo->periodo }})
                                · {{ \App\Models\HorarioClase::DIAS[$bloque->dia] }} {{ substr($bloque->hora_inicio, 0, 5) }}–{{ substr($bloque->hora_fin, 0, 5) }}
                                · {{ $bloque->espacio->nombre }}
                            </option>
                        @endforeach
                    </select>
                    @error('horario_clase_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control @error('fecha') is-invalid @enderror" value="{{ old('fecha') }}">
                    @error('fecha')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                    <div class="form-text">La fecha debe caer en el día de la semana del bloque elegido.</div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <h2 class="h6 mb-1">Momentos del pase de lista</h2>
                        <p class="text-muted small">Elige a qué hora (hora local) se tomará la imagen de cada momento. Deben estar dentro del horario de la sesión y en orden: inicio, desarrollo y cierre. Al elegir el bloque se sugieren tres horas que puedes cambiar.</p>

                        @include('sesiones._momentos', ['valores' => []])
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Observaciones (opcional)</label>
                    <textarea name="observaciones" rows="2" class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones') }}</textarea>
                    @error('observaciones')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <p class="text-muted small">La sesión guarda una copia del grupo, el espacio, el horario y los inscritos que la clase tiene hoy. Si después cambias la clase, esta sesión no se modifica. Las horas de los momentos se pueden cambiar despues desde la sesión.</p>

                <button type="submit" class="btn btn-success">Guardar</button>
                <a href="{{ route('sesiones.index') }}" class="btn btn-secondary">Cancelar</a>
            </form>

            <script>
                // Sugiere inicio, mitad y fin del bloque mientras el profesor no haya escrito sus propias horas
                (function () {
                    const selector = document.querySelector('select[name=horario_clase_id]');
                    const campos = {
                        inicio: document.querySelector('[name="momentos[inicio]"]'),
                        desarrollo: document.querySelector('[name="momentos[desarrollo]"]'),
                        cierre: document.querySelector('[name="momentos[cierre]"]')
                    };

                    let tocado = campos.inicio.value !== '' || campos.desarrollo.value !== '' || campos.cierre.value !== '';

                    Object.values(campos).forEach(function (campo) {
                        campo.addEventListener('input', function () { tocado = true; });
                    });

                    function aMinutos(hora) {
                        const partes = hora.split(':');
                        return parseInt(partes[0]) * 60 + parseInt(partes[1]);
                    }

                    function aHora(minutos) {
                        return String(Math.floor(minutos / 60)).padStart(2, '0') + ':' + String(minutos % 60).padStart(2, '0');
                    }

                    selector.addEventListener('change', function () {
                        const opcion = selector.options[selector.selectedIndex];

                        if (tocado || !opcion.dataset.inicio) {
                            return;
                        }

                        const inicio = aMinutos(opcion.dataset.inicio);
                        const fin = aMinutos(opcion.dataset.fin);

                        campos.inicio.value = aHora(inicio);
                        campos.desarrollo.value = aHora(Math.floor((inicio + fin) / 2));
                        campos.cierre.value = aHora(fin);
                    });
                })();
            </script>
        @endif

    </div>
</div>
@endsection
