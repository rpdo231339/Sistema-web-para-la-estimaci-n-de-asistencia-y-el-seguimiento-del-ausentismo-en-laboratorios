@extends('layouts.app')

@section('titulo', 'Evaluaciones del conteo')

@section('content')
@php
    $etiquetasOclusion = ['nula' => 'Nula', 'parcial' => 'Parcial', 'alta' => 'Alta'];
    $fmt = fn ($v, $d = 2) => $v === null ? '—' : number_format($v, $d);
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Evaluaciones del conteo automático</h1>

    <div class="d-flex gap-2">
        <a href="{{ route('evaluaciones.exportar', request()->query()) }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-file-csv"></i> Exportar CSV
        </a>
        <a href="{{ route('evaluaciones.create') }}" class="btn btn-success">
            <i class="fa-solid fa-plus"></i> Nueva evaluación
        </a>
    </div>
</div>

<p class="text-muted small">
    Cada evaluación compara el conteo automático de una imagen estática con el número de personas visibles que cuentas tú.
    Las métricas se calculan imagen por imagen y no suman personas entre imágenes.
</p>

<form action="{{ route('evaluaciones.index') }}" method="GET" class="card card-body shadow-sm mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Ambiente</label>
            <select name="ambiente" class="form-select">
                <option value="">Todos</option>
                <option value="aula" {{ request('ambiente') === 'aula' ? 'selected' : '' }}>Aula</option>
                <option value="laboratorio" {{ request('ambiente') === 'laboratorio' ? 'selected' : '' }}>Laboratorio</option>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Oclusión</label>
            <select name="oclusion" class="form-select">
                <option value="">Todas</option>
                @foreach ($etiquetasOclusion as $valor => $texto)
                    <option value="{{ $valor }}" {{ request('oclusion') === $valor ? 'selected' : '' }}>{{ $texto }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4 d-flex gap-2">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <a href="{{ route('evaluaciones.index') }}" class="btn btn-secondary">Limpiar</a>
        </div>
    </div>
</form>

{{-- Resumen --}}
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h5">Resumen de las evaluaciones procesadas</h2>

        @if ($general['n'] === 0)
            <p class="text-muted mb-0">Todavía no hay evaluaciones con resultado procesado para estos filtros.</p>
        @else
            <div class="row text-center mb-3">
                <div class="col-6 col-md-2">
                    <div class="fs-4 fw-semibold">{{ $general['n'] }}</div>
                    <div class="small text-muted">Imágenes</div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="fs-4 fw-semibold">{{ $fmt($general['mae']) }}</div>
                    <div class="small text-muted">Error absoluto medio</div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="fs-4 fw-semibold">{{ $fmt($general['sesgo']) }}</div>
                    <div class="small text-muted">Diferencia media (con signo)</div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="fs-4 fw-semibold">{{ $general['exactas'] }}</div>
                    <div class="small text-muted">Conteos exactos</div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="fs-4 fw-semibold">{{ $general['omisiones'] }} / {{ $general['duplicaciones'] }}</div>
                    <div class="small text-muted">Omisiones / duplicaciones</div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="fs-4 fw-semibold">{{ $fmt($general['tiempo_medio_ms'], 0) }} ms</div>
                    <div class="small text-muted">Tiempo medio</div>
                </div>
            </div>

            <div class="row g-3">
                @foreach ([['Por ambiente', $porAmbiente], ['Por oclusión', $porOclusion]] as [$titulo, $grupos])
                    <div class="col-md-6">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ $titulo }}</th>
                                        <th class="text-end">Imágenes</th>
                                        <th class="text-end">Error abs. medio</th>
                                        <th class="text-end">Dif. media</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($grupos as $nombre => $r)
                                        <tr>
                                            <td>{{ ucfirst($nombre) }}</td>
                                            <td class="text-end">{{ $r['n'] }}</td>
                                            <td class="text-end">{{ $fmt($r['mae']) }}</td>
                                            <td class="text-end">{{ $fmt($r['sesgo']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="small text-muted mt-3 mb-0">
                Diferencia = conteo automático − referencia manual: positiva si el sistema cuenta de más, negativa si cuenta de menos.
                Las omisiones y duplicaciones suman solo lo que capturaste en cada evaluación.
            </p>
        @endif
    </div>
</div>

{{-- Listado --}}
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Ambiente</th>
                    <th>Oclusión</th>
                    <th class="text-end">Referencia</th>
                    <th class="text-end">Automático</th>
                    <th class="text-end">Diferencia</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($evaluaciones as $e)
                    @php $p = $e->procesamiento; $d = $e->diferencia(); @endphp
                    <tr>
                        <td>{{ $e->id }}</td>
                        <td>{{ ucfirst($e->ambiente) }}</td>
                        <td>{{ $etiquetasOclusion[$e->oclusion] }}</td>
                        <td class="text-end">{{ $e->referencia_manual }}</td>
                        <td class="text-end">{{ $p->estado === 'procesado' ? $p->conteo : '—' }}</td>
                        <td class="text-end">{{ $d === null ? '—' : ($d > 0 ? '+' . $d : $d) }}</td>
                        <td>
                            @if ($p->estado === 'procesado')
                                <span class="badge text-bg-success">Procesada</span>
                            @elseif ($p->estado === 'error')
                                <span class="badge text-bg-danger">Error</span>
                            @else
                                <span class="badge text-bg-warning">{{ $p->estado === 'pendiente' ? 'En espera' : 'Analizando…' }}</span>
                            @endif
                        </td>
                        <td><a href="{{ route('evaluaciones.show', $e->id) }}" class="btn btn-primary btn-sm">Ver</a></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Todavía no hay evaluaciones</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($hayEnCurso)
    <script>
        // Hay analisis en curso: la pagina se actualiza sola
        setTimeout(function () { window.location.reload(); }, 5000);
    </script>
@endif
@endsection
