@extends('layouts.app')

@section('titulo', 'Pases de la sesión')

@section('content')
@php
    $etiquetasEstado = [
        'sin_evidencia' => ['Sin evidencia', 'secondary'],
        'procesado' => ['Procesado', 'info'],
        'validado' => ['Validado', 'success'],
        'con_incidencia' => ['Con incidencia', 'danger'],
    ];
    $maxMb = round(config('evidencias.max_kb') / 1024);
    $zona = config('app.timezone_local', 'America/Mexico_City');

    $hayAnalisisEnCurso = $sesion->pases->contains(function ($p) {
        $actual = optional($p->evidenciaVigente)->procesamientoActual;

        return $actual && in_array($actual->estado, ['pendiente', 'en_proceso']);
    });
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Pases de lista · {{ $sesion->fecha->format('d/m/Y') }}</h1>
    <a href="{{ route('pases.index') }}" class="btn btn-secondary">Volver</a>
</div>

@if ($hayAnalisisEnCurso)
    @include('partials.analisis_en_curso')
@endif

{{-- Informacion de la clase (copia historica de la sesion) --}}
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row small">
            <div class="col-md-3"><span class="text-muted d-block">Asignatura</span>{{ data_get($sesion->contexto_historico, 'asignatura', '—') }}</div>
            <div class="col-md-3"><span class="text-muted d-block">Profesor</span>{{ data_get($sesion->contexto_historico, 'profesor', '—') }}</div>
            <div class="col-md-2"><span class="text-muted d-block">Grupo</span>{{ data_get($sesion->contexto_historico, 'grupo.clave', '—') }} · {{ $sesion->periodo }}</div>
            <div class="col-md-2"><span class="text-muted d-block">Espacio</span>{{ data_get($sesion->contexto_historico, 'espacio.nombre', '—') }}</div>
            <div class="col-md-2"><span class="text-muted d-block">Horario</span>{{ substr($sesion->hora_inicio, 0, 5) }}–{{ substr($sesion->hora_fin, 0, 5) }}</div>
        </div>
    </div>
</div>

{{-- Los tres pases --}}
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <h2 class="h5 mb-3">Momentos</h2>

        @error('analisis')
            <div class="alert alert-danger py-2">{{ $message }}</div>
        @enderror

        @if ($sesion->pases->isEmpty())
            <p class="text-muted mb-0">El profesor todavía no define la hora de los tres momentos. Cuando lo haga podrás cargar las imágenes.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Momento</th>
                            <th>Hora prevista</th>
                            <th>Hora representada</th>
                            <th>Archivo</th>
                            <th>Conteo</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sesion->pases as $pase)
                            @php $vigente = $pase->evidencias->first(); @endphp
                            <tr>
                                <td>{{ \App\Models\PaseLista::MOMENTOS[$pase->momento] }}</td>
                                <td>{{ $pase->programadoLocal()->format('d/m/Y H:i') }}</td>
                                <td>{{ $vigente ? $vigente->capturadaLocal()->format('d/m/Y H:i') : '—' }}</td>
                                <td>
                                    @if ($vigente)
                                        <a href="{{ route('evidencias.archivo', $vigente->id) }}" target="_blank" rel="noopener">{{ $vigente->nombre_original }}</a>
                                        <span class="text-muted small d-block">Versión {{ $vigente->version }} · {{ $vigente->tamanoLegible() }}</span>
                                    @else
                                        <span class="text-muted">Sin archivo</span>
                                    @endif
                                </td>
                                <td>
                                    @php $proc = optional($pase->evidenciaVigente)->procesamientoActual; @endphp

                                    @if (!$vigente || !$proc)
                                        <span class="text-muted">Pendiente</span>
                                    @elseif ($proc->estado === 'procesado')
                                        <span class="fs-5 fw-semibold">{{ $proc->conteo }}</span>
                                        <span class="text-muted small d-block">{{ $proc->duracion_ms }} ms</span>
                                        @if (data_get($proc->datos_resultado, 'anotada'))
                                            <a href="{{ route('procesamientos.anotada', $proc->id) }}" target="_blank" rel="noopener" class="small">Ver detecciones</a>
                                        @endif
                                    @elseif ($proc->estado === 'error')
                                        <span class="badge text-bg-danger">Error</span>
                                        <span class="text-muted small d-block">{{ $proc->error }}</span>
                                    @else
                                        @include('partials.estado_analisis', ['estado' => $proc->estado])
                                        @if ($proc->estado === 'pendiente' && $proc->created_at->lt(now()->subMinutes(2)))
                                            <span class="text-danger small d-block">En espera {{ $proc->created_at->diffForHumans(null, true) }}. El procesador en segundo plano podría estar detenido; avisa al administrador.</span>
                                        @endif
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $etiquetasEstado[$pase->estado][1] }}">{{ $etiquetasEstado[$pase->estado][0] }}</span>
                                </td>
                                <td>
                                    @if ($vigente && !($proc && in_array($proc->estado, ['pendiente', 'en_proceso'])))
                                        <form action="{{ route('pases.analizar', $pase->id) }}" method="POST" class="mb-1" data-cargando="Iniciando…"
                                            @if ($proc && $proc->estado === 'procesado') onsubmit="return confirm('Un análisis nuevo reemplaza el resultado actual y exige revisarlo de nuevo. ¿Continuar?');" @endif>
                                            @csrf
                                            <input type="hidden" name="clave_operacion" value="{{ hash('sha256', (string) \Illuminate\Support\Str::uuid()) }}">
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                {{ !$proc ? 'Ejecutar análisis' : ($proc->estado === 'error' ? 'Reintentar' : 'Volver a ejecutar') }}
                                            </button>
                                        </form>
                                    @endif

                                    @if ($vigente && $pase->estado === 'sin_evidencia')
                                        <details>
                                            <summary class="btn btn-outline-warning btn-sm">Visibilidad insuficiente</summary>

                                            <form action="{{ route('pases.visibilidad', $pase->id) }}" method="POST" class="mt-2" style="min-width: 230px;">
                                                @csrf
                                                <textarea name="motivo" rows="2" class="form-control form-control-sm" placeholder="¿Por qué no se ve bien?"></textarea>
                                                <button type="submit" class="btn btn-warning btn-sm mt-2">Marcar para revisión</button>
                                            </form>
                                        </details>
                                    @endif
                                </td>
                            </tr>

                            @if ($pase->observaciones)
                                <tr>
                                    <td></td>
                                    <td colspan="6" class="small text-muted" style="white-space: pre-line;">{{ $pase->observaciones }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="text-muted small mt-2 mb-0">El conteo se calcula después, al ejecutar el análisis. Un momento sin imagen no equivale a un conteo de cero.</p>
        @endif
    </div>
</div>

{{-- Formulario de carga --}}
@if ($sesion->pases->isNotEmpty() && $sesion->estado !== 'cancelada')
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h2 class="h5 mb-1">Cargar imagen</h2>
            <p class="text-muted small">Formatos JPG, JPEG y PNG, hasta {{ $maxMb }} MB. Si el momento ya tiene una imagen, la nueva se guarda como una versión nueva y pasa a ser la vigente. Cargar un archivo no ejecuta el análisis.</p>

            <form action="{{ route('pases.subir', $sesion->id) }}" method="POST" enctype="multipart/form-data" id="form-carga" data-cargando="Cargando imagen…">
                @csrf

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Momento</label>
                        <select name="momento" id="momento" class="form-select @error('momento') is-invalid @enderror">
                            @foreach ($sesion->pases as $pase)
                                <option value="{{ $pase->momento }}"
                                    data-programado="{{ $pase->programadoLocal()->format('Y-m-d\TH:i') }}"
                                    data-version="{{ optional($pase->evidencias->first())->version ?? 0 }}"
                                    {{ old('momento') === $pase->momento ? 'selected' : '' }}>
                                    {{ \App\Models\PaseLista::MOMENTOS[$pase->momento] }} · {{ $pase->programadoLocal()->format('H:i') }}
                                </option>
                            @endforeach
                        </select>
                        @error('momento')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Hora representada en la imagen</label>
                        <input type="datetime-local" name="capturada_en" id="capturada_en" class="form-control @error('capturada_en') is-invalid @enderror" value="{{ old('capturada_en') }}">
                        @error('capturada_en')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Hora local ({{ $zona }}). Debe ser la de la sesión.</div>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Archivo</label>
                        <input type="file" name="archivo" accept="image/jpeg,image/png" class="form-control @error('archivo') is-invalid @enderror">
                        @error('archivo')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Observaciones (opcional)</label>
                    <textarea name="observaciones" rows="2" class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones') }}</textarea>
                    @error('observaciones')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-success">Cargar imagen</button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const formulario = document.getElementById('form-carga');
            const momento = document.getElementById('momento');
            const hora = document.getElementById('capturada_en');

            let tocada = hora.value !== '';

            hora.addEventListener('input', function () { tocada = true; });

            // Sugiere la hora prevista del momento elegido mientras no se escriba otra
            function sugerir() {
                const opcion = momento.options[momento.selectedIndex];

                if (!tocada && opcion) {
                    hora.value = opcion.dataset.programado;
                }
            }

            momento.addEventListener('change', sugerir);
            sugerir();

            // Aviso antes de sustituir una imagen ya cargada
            formulario.addEventListener('submit', function (evento) {
                const opcion = momento.options[momento.selectedIndex];
                const version = parseInt(opcion.dataset.version) || 0;

                if (version > 0) {
                    const mensaje = 'Este momento ya tiene la versión ' + version + '. La imagen nueva la sustituirá como vigente y dejará obsoleto su resultado. ¿Continuar?';

                    if (!confirm(mensaje)) {
                        evento.preventDefault();
                    }
                }
            });
        })();
    </script>
@endif

{{-- Historial de versiones --}}
@php
    $hayVersiones = $sesion->pases->contains(function ($pase) {
        return $pase->evidencias->isNotEmpty();
    });
@endphp

@if ($hayVersiones)
    <div class="card shadow-sm">
        <div class="card-body">
            <h2 class="h5 mb-3">Historial de versiones</h2>

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Momento</th>
                            <th>Versión</th>
                            <th>Archivo</th>
                            <th>Hora representada</th>
                            <th>Cargada por</th>
                            <th>Fecha de carga</th>
                            <th>Tamaño</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sesion->pases as $pase)
                            @foreach ($pase->evidencias as $evidencia)
                                <tr>
                                    <td>{{ \App\Models\PaseLista::MOMENTOS[$pase->momento] }}</td>
                                    <td>
                                        {{ $evidencia->version }}
                                        @if ($loop->first)
                                            <span class="badge text-bg-success">Vigente</span>
                                        @else
                                            <span class="badge text-bg-light border">Anterior</span>
                                        @endif
                                    </td>
                                    <td><a href="{{ route('evidencias.archivo', $evidencia->id) }}" target="_blank" rel="noopener">{{ $evidencia->nombre_original }}</a></td>
                                    <td>{{ $evidencia->capturadaLocal()->format('d/m/Y H:i') }}</td>
                                    <td>{{ $evidencia->cargadoPor->nombre ?? '—' }}</td>
                                    <td>{{ $evidencia->cargadaLocal()->format('d/m/Y H:i') }}</td>
                                    <td>{{ $evidencia->tamanoLegible() }}</td>
                                    <td class="small text-muted">{{ $evidencia->ancho }}×{{ $evidencia->alto }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif
@if ($hayAnalisisEnCurso)
    <script>
        // Mientras el analisis esta en curso, la pagina se actualiza sola cada 5 segundos
        setTimeout(function () { window.location.reload(); }, 5000);
    </script>
@endif
@endsection
