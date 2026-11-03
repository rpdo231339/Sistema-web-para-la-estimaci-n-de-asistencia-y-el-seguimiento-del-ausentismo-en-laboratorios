@extends('layouts.app')

@section('titulo', 'Evaluación')

@section('content')
@php
    $p = $evaluacion->procesamiento;
    $d = $evaluacion->diferencia();
    $zona = config('app.timezone_local', 'America/Mexico_City');
    $enCurso = in_array($p->estado, ['pendiente', 'en_proceso']);
    $anotada = data_get($p->datos_resultado, 'anotada');
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Evaluación #{{ $evaluacion->id }}</h1>
    <a href="{{ route('evaluaciones.index') }}" class="btn btn-secondary">Volver</a>
</div>

<div class="row g-3">

    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">{{ $anotada ? 'Detecciones del sistema' : 'Imagen de prueba' }}</h2>

                @if ($anotada)
                    <img src="{{ route('evaluaciones.anotada', $evaluacion->id) }}" alt="Imagen con las detecciones dibujadas" class="img-fluid rounded border mb-2">
                    <p class="small mb-0"><a href="{{ route('evaluaciones.imagen', $evaluacion->id) }}" target="_blank" rel="noopener">Ver la imagen original</a></p>
                @else
                    <img src="{{ route('evaluaciones.imagen', $evaluacion->id) }}" alt="Imagen de prueba" class="img-fluid rounded border">
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="h5">Condiciones y resultado</h2>

                @error('analisis')
                    <div class="alert alert-danger small">{{ $message }}</div>
                @enderror

                <dl class="row small mb-3">
                    <dt class="col-5">Ambiente</dt>
                    <dd class="col-7">{{ ucfirst($evaluacion->ambiente) }}</dd>

                    <dt class="col-5">Iluminación</dt>
                    <dd class="col-7">{{ $evaluacion->iluminacion }}</dd>

                    <dt class="col-5">Ángulo</dt>
                    <dd class="col-7">{{ $evaluacion->angulo }}</dd>

                    <dt class="col-5">Oclusión</dt>
                    <dd class="col-7">{{ ucfirst($evaluacion->oclusion) }}</dd>

                    <dt class="col-5">Registrada</dt>
                    <dd class="col-7">{{ $evaluacion->registradoPor->nombre ?? '—' }} · {{ $evaluacion->created_at->timezone($zona)->format('d/m/Y H:i') }}</dd>
                </dl>

                <div class="row text-center mb-2">
                    <div class="col-4">
                        <div class="fs-3 fw-semibold">{{ $evaluacion->referencia_manual }}</div>
                        <div class="small text-muted">Referencia manual</div>
                    </div>
                    <div class="col-4">
                        <div class="fs-3 fw-semibold">{{ $p->estado === 'procesado' ? $p->conteo : '—' }}</div>
                        <div class="small text-muted">Conteo automático</div>
                    </div>
                    <div class="col-4">
                        <div class="fs-3 fw-semibold">{{ $d === null ? '—' : ($d > 0 ? '+' . $d : $d) }}</div>
                        <div class="small text-muted">Diferencia</div>
                    </div>
                </div>

                @if ($p->estado === 'procesado')
                    <p class="small text-muted mb-0">
                        Tiempo de procesamiento: {{ $p->duracion_ms }} ms · modelo {{ data_get($p->datos_resultado, 'modelo.pesos', '—') }}
                    </p>
                @elseif ($p->estado === 'error')
                    <div class="alert alert-danger small mb-0">{{ $p->error }}</div>
                @else
                    <span class="badge text-bg-warning">{{ $p->estado === 'pendiente' ? 'En espera' : 'Analizando…' }}</span>
                    @if ($p->estado === 'pendiente' && $p->created_at->lt(now()->subMinutes(2)))
                        <span class="text-danger small d-block">En espera {{ $p->created_at->diffForHumans(null, true) }}. Comprueba que el procesador en segundo plano esté activo.</span>
                    @endif
                @endif
            </div>
        </div>

        @if ($p->estado === 'procesado')
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h5">Errores observados</h2>
                    <p class="text-muted small">Mira las detecciones y registra lo que ves. Si no hubo, escribe 0.</p>

                    <form action="{{ route('evaluaciones.update', $evaluacion->id) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Omisiones</label>
                                <input type="number" min="0" step="1" name="omisiones" class="form-control @error('omisiones') is-invalid @enderror" value="{{ old('omisiones', $evaluacion->omisiones) }}">
                                <div class="form-text">Personas visibles que no se detectaron.</div>
                                @error('omisiones')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-6 mb-3">
                                <label class="form-label">Duplicaciones</label>
                                <input type="number" min="0" step="1" name="duplicaciones" class="form-control @error('duplicaciones') is-invalid @enderror" value="{{ old('duplicaciones', $evaluacion->duplicaciones) }}">
                                <div class="form-text">Detecciones repetidas de una misma persona.</div>
                                @error('duplicaciones')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" rows="2" class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones', $evaluacion->observaciones) }}</textarea>
                            @error('observaciones')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                    </form>
                </div>
            </div>
        @endif

        @if (!$enCurso)
            <form action="{{ route('evaluaciones.analizar', $evaluacion->id) }}" method="POST"
                @if ($p->estado === 'procesado') onsubmit="return confirm('Un análisis nuevo reemplaza el resultado actual de esta evaluación. ¿Continuar?');" @endif>
                @csrf
                <input type="hidden" name="clave_operacion" value="{{ hash('sha256', (string) \Illuminate\Support\Str::uuid()) }}">
                <button type="submit" class="btn btn-outline-primary btn-sm">{{ $p->estado === 'error' ? 'Reintentar análisis' : 'Volver a analizar' }}</button>
            </form>
        @endif
    </div>

</div>

@if ($enCurso)
    <script>
        // Hay un analisis en curso: la pagina se actualiza sola
        setTimeout(function () { window.location.reload(); }, 5000);
    </script>
@endif
@endsection
