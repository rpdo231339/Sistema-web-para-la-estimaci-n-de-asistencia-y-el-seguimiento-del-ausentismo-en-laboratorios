@extends('layouts.app')

@section('titulo', 'Editar espacio')

@section('content')
<h1 class="h3 mb-3">Editar espacio</h1>

<div class="row g-3">

    <div class="{{ Auth::user()->rol === 'administrador' ? 'col-lg-7' : 'col-lg-8' }}">
        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('espacios.update', $espacio->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control @error('nombre') is-invalid @enderror" value="{{ old('nombre', $espacio->nombre) }}">
                        @error('nombre')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select @error('tipo') is-invalid @enderror">
                                <option value="aula" {{ old('tipo', $espacio->tipo) === 'aula' ? 'selected' : '' }}>Aula</option>
                                <option value="laboratorio" {{ old('tipo', $espacio->tipo) === 'laboratorio' ? 'selected' : '' }}>Laboratorio</option>
                            </select>
                            @error('tipo')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Estado</label>
                            <select name="activo" class="form-select @error('activo') is-invalid @enderror">
                                <option value="1" {{ (string) old('activo', $espacio->activo ? '1' : '0') === '1' ? 'selected' : '' }}>Activo</option>
                                <option value="0" {{ (string) old('activo', $espacio->activo ? '1' : '0') === '0' ? 'selected' : '' }}>Inactivo</option>
                            </select>
                            @error('activo')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Ubicación</label>
                        <input type="text" name="ubicacion" class="form-control @error('ubicacion') is-invalid @enderror" value="{{ old('ubicacion', $espacio->ubicacion) }}">
                        @error('ubicacion')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción (opcional)</label>
                        <textarea name="descripcion" rows="3" class="form-control @error('descripcion') is-invalid @enderror">{{ old('descripcion', $espacio->descripcion) }}</textarea>
                        @error('descripcion')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <a href="{{ route('espacios.index') }}" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>

    {{-- La asignacion de encargados solo la ve y la usa el administrador --}}
    @if (Auth::user()->rol === 'administrador')
        <div class="col-lg-5">
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Encargados asignados</h2>

                    <ul class="list-group mb-3">
                        @forelse ($asignados as $encargado)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span>
                                    {{ $encargado->nombre }}
                                    <small class="text-muted d-block">{{ $encargado->correo }}</small>
                                </span>

                                <form action="{{ route('espacios.encargados.quitar', [$espacio->id, $encargado->id]) }}" method="POST" class="form-confirmar" data-mensaje="¿Quitar a {{ $encargado->nombre }} de este espacio?">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm">Quitar</button>
                                </form>
                            </li>
                        @empty
                            <li class="list-group-item text-muted">Todavía no hay encargados asignados</li>
                        @endforelse
                    </ul>

                    <form action="{{ route('espacios.encargados.asignar', $espacio->id) }}" method="POST">
                        @csrf

                        <label class="form-label">Asignar un encargado</label>
                        <div class="input-group">
                            <select name="usuario_id" class="form-select @error('usuario_id') is-invalid @enderror">
                                <option value="">Selecciona un encargado</option>
                                @foreach ($candidatos as $candidato)
                                    <option value="{{ $candidato->id }}">{{ $candidato->nombre }} ({{ $candidato->correo }})</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-success">Asignar</button>
                        </div>
                        @error('usuario_id')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror

                        @if ($candidatos->isEmpty())
                            <div class="text-muted small mt-2">No hay más encargados activos disponibles. Créalos desde Usuarios.</div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    @endif

</div>

{{-- Clases vigentes que tienen al menos un bloque en este espacio (solo consulta) --}}
<div class="card shadow-sm mt-3">
    <div class="card-body">
        <h2 class="h5 mb-3">Clases vinculadas</h2>

        <form action="{{ route('espacios.edit', $espacio->id) }}" method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-6">
                <label class="form-label">Buscar</label>
                <input type="text" name="buscar" class="form-control" placeholder="Asignatura o profesor" value="{{ request('buscar') }}">
            </div>

            <div class="col-md-3">
                <label class="form-label">Periodo</label>
                <select name="periodo" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($periodosClases as $periodo)
                        <option value="{{ $periodo }}" {{ request('periodo') === $periodo ? 'selected' : '' }}>{{ $periodo }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="{{ route('espacios.edit', $espacio->id) }}" class="btn btn-secondary">Limpiar</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Asignatura</th>
                        <th>Profesor</th>
                        <th>Grupo</th>
                        <th>Periodo</th>
                        <th>Horario en este espacio</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clasesVinculadas as $clase)
                        <tr>
                            <td>{{ $clase->asignatura }}</td>
                            <td>{{ $clase->profesor->nombre }}</td>
                            <td>{{ $clase->grupo->clave }}</td>
                            <td>{{ $clase->grupo->periodo }}</td>
                            <td class="small">
                                @foreach ($clase->horariosActivos as $bloque)
                                    <div>
                                        {{ mb_substr(\App\Models\HorarioClase::DIAS[$bloque->dia], 0, 3) }}
                                        {{ substr($bloque->hora_inicio, 0, 5) }}–{{ substr($bloque->hora_fin, 0, 5) }}
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">Este espacio no tiene clases vigentes vinculadas</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $clasesVinculadas->links() }}
        </div>
    </div>
</div>

<script>
    // Confirmacion antes de quitar un encargado
    document.querySelectorAll('.form-confirmar').forEach(function (formulario) {
        formulario.addEventListener('submit', function (evento) {
            if (!confirm(formulario.dataset.mensaje)) {
                evento.preventDefault();
            }
        });
    });
</script>
@endsection
