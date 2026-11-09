{{-- Filtros y botones de exportacion. Variables: $ruta, $filtros, $opciones --}}
<form action="{{ route($ruta) }}" method="GET" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-2">
            <label class="form-label">Periodo</label>
            <select name="periodo" class="form-select">
                <option value="">Todos</option>
                @foreach ($opciones['periodos'] as $p)
                    <option value="{{ $p }}" {{ ($filtros['periodo'] ?? '') === $p ? 'selected' : '' }}>{{ $p }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" class="form-control" value="{{ $filtros['desde'] ?? '' }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" class="form-control" value="{{ $filtros['hasta'] ?? '' }}">
        </div>

        <div class="col-md-2">
            <label class="form-label">Grupo</label>
            <select name="grupo_id" class="form-select">
                <option value="">Todos</option>
                @foreach ($opciones['grupos'] as $g)
                    <option value="{{ $g->id }}" {{ (string) ($filtros['grupo_id'] ?? '') === (string) $g->id ? 'selected' : '' }}>{{ $g->clave }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Espacio</label>
            <select name="espacio_id" class="form-select">
                <option value="">Todos</option>
                @foreach ($opciones['espacios'] as $e)
                    <option value="{{ $e->id }}" {{ (string) ($filtros['espacio_id'] ?? '') === (string) $e->id ? 'selected' : '' }}>{{ $e->nombre }}</option>
                @endforeach
            </select>
        </div>

        @isset($tipos)
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="">Todos</option>
                    @foreach ($tipos as $clave => $texto)
                        <option value="{{ $clave }}" {{ ($filtros['tipo'] ?? '') === $clave ? 'selected' : '' }}>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
        @endisset

        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="{{ route($ruta) }}" class="btn btn-secondary">Limpiar</a>
        </div>
    </div>

    @error('hasta')
        <div class="text-danger small mt-2">{{ $message }}</div>
    @enderror
    @error('desde')
        <div class="text-danger small mt-2">{{ $message }}</div>
    @enderror
</form>

<div class="d-flex gap-2 mb-3">
    <a href="{{ route($ruta, array_merge($filtros, ['formato' => 'pdf'])) }}" class="btn btn-outline-danger btn-sm">
        <i class="fa-solid fa-file-pdf"></i> Descargar PDF
    </a>
    <a href="{{ route($ruta, array_merge($filtros, ['formato' => 'csv'])) }}" class="btn btn-outline-success btn-sm">
        <i class="fa-solid fa-file-csv"></i> Descargar CSV
    </a>
</div>
