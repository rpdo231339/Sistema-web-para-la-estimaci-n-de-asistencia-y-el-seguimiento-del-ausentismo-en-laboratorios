@php
    // Si la validacion fallo se recuperan las filas escritas; si no, las de la clase o una fila vacia
    $filas = old('horarios', $filasIniciales ?? [[]]);
    $siguiente = count($filas) ? max(array_keys($filas)) + 1 : 0;
    $erroresHorarios = collect($errors->get('horarios.*'))->flatten()->unique();
@endphp

<div class="mb-3">
    <label class="form-label">Asignatura</label>
    <input type="text" name="asignatura" class="form-control @error('asignatura') is-invalid @enderror" value="{{ old('asignatura', $clase->asignatura ?? '') }}">
    @error('asignatura')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="row">
    <div class="col-md-8 mb-3">
        <label class="form-label">Grupo</label>
        <select name="grupo_id" class="form-select @error('grupo_id') is-invalid @enderror">
            <option value="">Selecciona un grupo</option>
            @foreach ($grupos as $grupo)
                <option value="{{ $grupo->id }}" {{ (string) old('grupo_id', $clase->grupo_id ?? '') === (string) $grupo->id ? 'selected' : '' }}>
                    {{ $grupo->clave }} · {{ $grupo->programa }} · {{ $grupo->periodo }}{{ $grupo->activo ? '' : ' (inactivo)' }}
                </option>
            @endforeach
        </select>
        @error('grupo_id')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
        <div class="form-text">El periodo de la clase es el del grupo que elijas.</div>
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Total de inscritos</label>
        <input type="number" name="inscritos" min="1" max="{{ config('clases.max_inscritos', 30) }}" step="1" class="form-control @error('inscritos') is-invalid @enderror" value="{{ old('inscritos', $clase->inscritos ?? '') }}">
        @error('inscritos')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Observaciones (opcional)</label>
    <textarea name="observaciones" rows="2" class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones', $clase->observaciones ?? '') }}</textarea>
    @error('observaciones')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="h6 mb-0">Horario semanal</h2>
            <button type="button" id="btn-agregar-fila" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-plus"></i> Agregar bloque
            </button>
        </div>

        <p class="text-muted small">Registra los bloques del horario que ya tienes asignado. Una clase puede tener varios bloques, incluso en espacios distintos.</p>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-2">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Espacio</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="cuerpo-horarios">
                    @foreach ($filas as $i => $fila)
                        @include('clases._fila', ['i' => $i, 'fila' => $fila, 'espacios' => $espacios])
                    @endforeach
                </tbody>
            </table>
        </div>

        @error('horarios')
            <div class="text-danger small">{{ $message }}</div>
        @enderror

        @foreach ($erroresHorarios as $mensaje)
            <div class="text-danger small">{{ $mensaje }}</div>
        @endforeach
    </div>
</div>

{{-- Plantilla para los bloques que se agregan con el boton --}}
<template id="plantilla-fila">
    @include('clases._fila', ['i' => '__I__', 'fila' => [], 'espacios' => $espacios])
</template>

<script>
    (function () {
        let siguiente = {{ $siguiente }};
        const cuerpo = document.getElementById('cuerpo-horarios');
        const plantilla = document.getElementById('plantilla-fila').innerHTML;

        document.getElementById('btn-agregar-fila').addEventListener('click', function () {
            cuerpo.insertAdjacentHTML('beforeend', plantilla.replaceAll('__I__', siguiente));
            siguiente++;
        });

        cuerpo.addEventListener('click', function (evento) {
            const boton = evento.target.closest('.btn-quitar-fila');

            if (!boton) {
                return;
            }

            if (cuerpo.querySelectorAll('.fila-horario').length === 1) {
                alert('La clase necesita al menos un bloque de horario.');
                return;
            }

            boton.closest('tr').remove();
        });
    })();
</script>
