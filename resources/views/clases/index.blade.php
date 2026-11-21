@extends('layouts.app')

@section('titulo', 'Mis clases')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Mis clases</h1>

    <a href="{{ route('clases.create') }}" class="btn btn-success">
        <i class="fa-solid fa-plus"></i> Nueva clase
    </a>
</div>

<form action="{{ route('clases.index') }}" method="GET" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label">Buscar</label>
            <input type="text" name="buscar" class="form-control" placeholder="Asignatura" value="{{ request('buscar') }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Periodo</label>
            <select name="periodo" class="form-select">
                <option value="">Todos</option>
                @foreach ($periodos as $periodo)
                    <option value="{{ $periodo }}" {{ request('periodo') === $periodo ? 'selected' : '' }}>{{ $periodo }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
                <option value="">Todas</option>
                <option value="vigente" {{ request('estado') === 'vigente' ? 'selected' : '' }}>Vigentes</option>
                <option value="inactiva" {{ request('estado') === 'inactiva' ? 'selected' : '' }}>Inactivas</option>
            </select>
        </div>

        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="{{ route('clases.index') }}" class="btn btn-secondary">Limpiar</a>
        </div>
    </div>
</form>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Asignatura</th>
                    <th>Grupo</th>
                    <th>Periodo</th>
                    <th>Inscritos</th>
                    <th>Horario</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($clases as $clase)
                    <tr>
                        <td>{{ $clase->asignatura }}</td>
                        <td>{{ $clase->grupo->clave }} <span class="text-muted small d-block">{{ $clase->grupo->programa }}</span></td>
                        <td>{{ $clase->grupo->periodo }}</td>
                        <td>{{ $clase->inscritos }}</td>
                        <td class="small">
                            @foreach ($clase->horariosActivos as $bloque)
                                <div>
                                    {{ mb_substr(\App\Models\HorarioClase::DIAS[$bloque->dia], 0, 3) }}
                                    {{ substr($bloque->hora_inicio, 0, 5) }}–{{ substr($bloque->hora_fin, 0, 5) }}
                                    · {{ $bloque->espacio->nombre }}
                                </div>
                            @endforeach
                        </td>
                        <td>
                            @if ($clase->vigente)
                                <span class="badge text-bg-success">Vigente</span>
                            @else
                                <span class="badge text-bg-secondary">Inactiva</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('clases.edit', $clase->id) }}" class="btn btn-warning btn-sm">Editar</a>

                            @if ($clase->vigente)
                                <form action="{{ route('clases.estado', $clase->id) }}" method="POST" style="display:inline;" class="form-confirmar" data-mensaje="¿Desactivar la clase {{ $clase->asignatura }}? Se conservará su historial.">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-danger btn-sm">Desactivar</button>
                                </form>
                            @else
                                <form action="{{ route('clases.estado', $clase->id) }}" method="POST" style="display:inline;" class="form-confirmar" data-mensaje="¿Reactivar la clase {{ $clase->asignatura }}?">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-success btn-sm">Reactivar</button>
                                </form>
                            @endif

                            @if ($eliminables->contains($clase->id))
                                <form action="{{ route('clases.eliminar', $clase->id) }}" method="POST" style="display:inline;" class="form-confirmar" data-mensaje="¿Eliminar definitivamente la clase {{ $clase->asignatura }}? Esta acción no se puede deshacer.">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="No tiene sesiones: se puede eliminar por completo"><i class="fa-solid fa-trash"></i> Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Todavía no tienes clases registradas</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="d-flex justify-content-between align-items-center mt-3">
    <span class="text-muted small">{{ $clases->total() }} clase(s) en total</span>
    {{ $clases->links() }}
</div>

<script>
    // Confirmacion antes de desactivar o reactivar
    document.querySelectorAll('.form-confirmar').forEach(function (formulario) {
        formulario.addEventListener('submit', function (evento) {
            if (!confirm(formulario.dataset.mensaje)) {
                evento.preventDefault();
            }
        });
    });
</script>
@endsection
