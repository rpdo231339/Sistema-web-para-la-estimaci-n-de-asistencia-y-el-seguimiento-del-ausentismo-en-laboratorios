@extends('layouts.app')

@section('titulo', 'Sesión')

@section('content')
@php
    $asistencia = $sesion->asistencia;
    $cancelada = $sesion->estado === 'cancelada';
    $confirmada = $asistencia && $asistencia->estado === 'confirmado';
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Sesión del {{ $sesion->fecha->format('d/m/Y') }}</h1>

    <a href="{{ route('sesiones.index') }}" class="btn btn-secondary">Volver</a>
</div>

<div class="row g-3">

    {{-- Datos de la sesion (copia historica) --}}
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h2 class="h5 mb-0">{{ data_get($sesion->contexto_historico, 'asignatura', '—') }}</h2>

                    @if ($sesion->estado === 'programada')
                        <span class="badge text-bg-primary">Programada</span>
                    @elseif ($sesion->estado === 'realizada')
                        <span class="badge text-bg-success">Realizada</span>
                    @else
                        <span class="badge text-bg-secondary">Cancelada</span>
                    @endif
                </div>

                <dl class="row small mb-3">
                    <dt class="col-5">Grupo</dt>
                    <dd class="col-7">{{ data_get($sesion->contexto_historico, 'grupo.clave', '—') }} · {{ data_get($sesion->contexto_historico, 'grupo.programa', '') }}</dd>

                    <dt class="col-5">Periodo</dt>
                    <dd class="col-7">{{ $sesion->periodo }}</dd>

                    <dt class="col-5">Espacio</dt>
                    <dd class="col-7">{{ data_get($sesion->contexto_historico, 'espacio.nombre', '—') }}</dd>

                    <dt class="col-5">Horario</dt>
                    <dd class="col-7">{{ substr($sesion->hora_inicio, 0, 5) }}–{{ substr($sesion->hora_fin, 0, 5) }}</dd>

                    <dt class="col-5">Inscritos</dt>
                    <dd class="col-7">
                        {{ $sesion->inscritos }}
                        @if ($sesion->clase && (int) $sesion->clase->inscritos !== (int) $sesion->inscritos)
                            <span class="text-muted d-block">La clase tiene hoy {{ $sesion->clase->inscritos }}; esta sesión conserva su valor original.</span>
                        @endif
                    </dd>

                    @if ($sesion->observaciones)
                        <dt class="col-5">Observaciones</dt>
                        <dd class="col-7" style="white-space: pre-line;">{{ $sesion->observaciones }}</dd>
                    @endif
                </dl>

                @if (!$cancelada)
                    <a href="{{ route('sesiones.edit', $sesion->id) }}" class="btn btn-warning btn-sm">Editar</a>

                    <details class="mt-3" {{ $errors->has('motivo_cancelacion') ? 'open' : '' }}>
                        <summary class="btn btn-outline-danger btn-sm">Cancelar sesión</summary>

                        <form action="{{ route('sesiones.cancelar', $sesion->id) }}" method="POST" class="mt-2">
                            @csrf
                            <label class="form-label small">Motivo de la cancelación</label>
                            <textarea name="motivo_cancelacion" rows="2" class="form-control form-control-sm @error('motivo_cancelacion') is-invalid @enderror">{{ old('motivo_cancelacion') }}</textarea>
                            @error('motivo_cancelacion')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                            <button type="submit" class="btn btn-danger btn-sm mt-2">Confirmar cancelación</button>
                        </form>
                    </details>
                @else
                    <form action="{{ route('sesiones.reactivar', $sesion->id) }}" method="POST" onsubmit="return confirm('¿Reactivar esta sesión?');">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">Reactivar sesión</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Registro agregado de asistencia --}}
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h2 class="h5 mb-0">Asistencia del profesor</h2>

                    @if (!$asistencia)
                        <span class="badge text-bg-light border">Sin registro</span>
                    @elseif ($asistencia->estado === 'borrador')
                        <span class="badge text-bg-warning">Borrador</span>
                    @elseif ($asistencia->estado === 'pendiente_revision')
                        <span class="badge text-bg-info">Pendiente de revisión</span>
                    @else
                        <span class="badge text-bg-success">Confirmado</span>
                    @endif
                </div>

                @if ($cancelada)
                    <p class="text-muted mb-0">La sesión está cancelada y no admite registro de asistencia. Se excluye de los indicadores.</p>

                @elseif ($confirmada)
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <div class="fs-3 fw-semibold">{{ $asistencia->asistentes }}</div>
                            <div class="small text-muted">Asistentes</div>
                        </div>
                        <div class="col-4">
                            <div class="fs-3 fw-semibold">{{ $asistencia->justificados }}</div>
                            <div class="small text-muted">Justificados</div>
                        </div>
                        <div class="col-4">
                            <div class="fs-3 fw-semibold">{{ $asistencia->faltas }}</div>
                            <div class="small text-muted">Faltas</div>
                        </div>
                    </div>

                    <p class="small text-muted mb-1">
                        Total {{ $asistencia->asistentes + $asistencia->justificados + $asistencia->faltas }} de {{ $sesion->inscritos }} inscritos.
                        Confirmado por {{ $asistencia->confirmadoPor->nombre ?? '—' }}
                        el {{ $asistencia->confirmado_en->timezone(config('app.timezone_local', 'America/Mexico_City'))->format('d/m/Y H:i') }} (hora de México).
                    </p>

                    @if ($asistencia->observaciones)
                        <p class="small mb-3" style="white-space: pre-line;">{{ $asistencia->observaciones }}</p>
                    @endif

                    <details {{ $errors->has('motivo_reapertura') ? 'open' : '' }}>
                        <summary class="btn btn-outline-secondary btn-sm">Reabrir para revisión</summary>

                        <form action="{{ route('sesiones.asistencia.reabrir', $sesion->id) }}" method="POST" class="mt-2">
                            @csrf
                            <input type="hidden" name="version" value="{{ $asistencia->version }}">

                            <label class="form-label small">Motivo de la reapertura</label>
                            <textarea name="motivo_reapertura" rows="2" class="form-control form-control-sm @error('motivo_reapertura') is-invalid @enderror">{{ old('motivo_reapertura') }}</textarea>
                            @error('motivo_reapertura')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror

                            <button type="submit" class="btn btn-secondary btn-sm mt-2">Reabrir</button>
                        </form>
                    </details>

                @else
                    <form action="{{ route('sesiones.asistencia.guardar', $sesion->id) }}" method="POST">
                        @csrf

                        @if ($asistencia)
                            <input type="hidden" name="version" value="{{ old('version', $asistencia->version) }}">
                        @endif

                        <p class="text-muted small">Resumen de la sesión según tu pase de lista externo. Al confirmar, los tres valores deben sumar los {{ $sesion->inscritos }} inscritos.</p>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Asistentes</label>
                                <input type="number" min="0" step="1" name="asistentes" class="form-control campo-cantidad @error('asistentes') is-invalid @enderror" value="{{ old('asistentes', $asistencia->asistentes ?? '') }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Justificados</label>
                                <input type="number" min="0" step="1" name="justificados" class="form-control campo-cantidad @error('justificados') is-invalid @enderror" value="{{ old('justificados', $asistencia->justificados ?? '') }}">
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label">Faltas</label>
                                <input type="number" min="0" step="1" name="faltas" class="form-control campo-cantidad @error('faltas') is-invalid @enderror" value="{{ old('faltas', $asistencia->faltas ?? '') }}">
                            </div>
                        </div>

                        @error('asistentes')
                            <div class="text-danger small mb-1">{{ $message }}</div>
                        @enderror
                        @error('justificados')
                            <div class="text-danger small mb-1">{{ $message }}</div>
                        @enderror
                        @error('faltas')
                            <div class="text-danger small mb-1">{{ $message }}</div>
                        @enderror

                        <p class="small mb-3">Suma actual: <span id="suma-total">0</span> de {{ $sesion->inscritos }}</p>

                        <div class="mb-3">
                            <label class="form-label">Observaciones (opcional)</label>
                            <textarea name="observaciones" rows="2" class="form-control @error('observaciones') is-invalid @enderror">{{ old('observaciones', $asistencia->observaciones ?? '') }}</textarea>
                            @error('observaciones')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" name="accion" value="borrador" class="btn btn-outline-primary">Guardar borrador</button>
                        <button type="submit" name="accion" value="confirmar" class="btn btn-success" onclick="return confirm('¿Confirmar la asistencia de esta sesión?');">Confirmar asistencia</button>
                    </form>

                    <script>
                        // Muestra la suma mientras se capturan las cantidades
                        (function () {
                            const inscritos = {{ $sesion->inscritos }};
                            const campos = document.querySelectorAll('.campo-cantidad');
                            const total = document.getElementById('suma-total');

                            function actualizar() {
                                let suma = 0;

                                campos.forEach(function (campo) {
                                    suma += parseInt(campo.value) || 0;
                                });

                                total.textContent = suma;
                                total.className = suma === inscritos ? 'text-success fw-bold' : 'text-danger fw-bold';
                            }

                            campos.forEach(function (campo) {
                                campo.addEventListener('input', actualizar);
                            });

                            actualizar();
                        })();
                    </script>
                @endif
            </div>
        </div>
    </div>

</div>
{{-- Tres momentos del pase de lista --}}
@php
    $pases = $sesion->pases;
    $etiquetasEstado = [
        'sin_evidencia' => ['Sin evidencia', 'secondary'],
        'procesado' => ['Procesado', 'info'],
        'validado' => ['Validado', 'success'],
        'con_incidencia' => ['Con incidencia', 'danger'],
    ];

    if ($pases->isNotEmpty()) {
        $valores = $pases->mapWithKeys(function ($pase) {
            return [$pase->momento => $pase->programadoLocal()->format('H:i')];
        })->all();
    } else {
        // Sugerencia: inicio, mitad y fin del horario de la sesion
        $ini = \Carbon\Carbon::createFromFormat('H:i', substr($sesion->hora_inicio, 0, 5));
        $fin = \Carbon\Carbon::createFromFormat('H:i', substr($sesion->hora_fin, 0, 5));
        $mitad = $ini->copy()->addMinutes((int) floor($ini->diffInMinutes($fin) / 2));

        $valores = ['inicio' => $ini->format('H:i'), 'desarrollo' => $mitad->format('H:i'), 'cierre' => $fin->format('H:i')];
    }
@endphp

<div class="card shadow-sm mt-3">
    <div class="card-body">
        <h2 class="h5 mb-1">Pases de lista</h2>
        <p class="text-muted small">Horas en {{ config('app.timezone_local', 'America/Mexico_City') }}. Cada momento permanece «sin evidencia» hasta que el encargado cargue su imagen y se procese; después tú revisas y validas el conteo.</p>

        @if ($pases->isNotEmpty())
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-3">
                    <thead>
                        <tr>
                            <th>Momento</th>
                            <th>Hora programada</th>
                            <th>Estado</th>
                            <th>Evidencia</th>
                            <th>Revisión</th>
                            <th>Versión de la programación</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pases as $pase)
                            <tr>
                                <td>{{ \App\Models\PaseLista::MOMENTOS[$pase->momento] }}</td>
                                <td>{{ $pase->programadoLocal()->format('d/m/Y H:i') }}</td>
                                <td>
                                    <span class="badge text-bg-{{ $etiquetasEstado[$pase->estado][1] }}">{{ $etiquetasEstado[$pase->estado][0] }}</span>
                                </td>
                                <td>
                                    @if ($pase->evidenciaVigente)
                                        <a href="{{ route('evidencias.archivo', $pase->evidenciaVigente->id) }}" target="_blank" rel="noopener">Ver imagen (v{{ $pase->evidenciaVigente->version }})</a>
                                        <span class="text-muted small d-block">Representa las {{ $pase->evidenciaVigente->capturadaLocal()->format('d/m/Y H:i') }}</span>

                                        @php $proc = $pase->evidenciaVigente->procesamientoActual; @endphp
                                        @if ($proc && $proc->estado === 'procesado')
                                            <span class="small d-block">
                                                <strong>Conteo automático: {{ $proc->conteo }}</strong>
                                                @if (data_get($proc->datos_resultado, 'anotada'))
                                                    · <a href="{{ route('procesamientos.anotada', $proc->id) }}" target="_blank" rel="noopener">ver detecciones</a>
                                                @endif
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-muted">Sin imagen</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $procRev = optional($pase->evidenciaVigente)->procesamientoActual;
                                        $rev = ($procRev && $procRev->estado === 'procesado') ? $procRev->revisionActual : null;
                                    @endphp

                                    @if ($rev && $rev->decision === 'validado')
                                        <span class="small d-block"><strong>Validado: {{ $rev->conteo_revisado }}</strong>
                                            @if ((int) $rev->conteo_revisado !== (int) $procRev->conteo)
                                                <span class="text-muted">(automático {{ $procRev->conteo }})</span>
                                            @endif
                                        </span>
                                    @elseif ($rev)
                                        <span class="small d-block text-danger">Observado</span>
                                    @endif

                                    @if ($procRev && $procRev->estado === 'procesado' && !$cancelada)
                                        <a href="{{ route('sesiones.revision', [$sesion->id, $pase->id]) }}" class="btn btn-sm {{ $rev ? 'btn-outline-secondary' : 'btn-primary' }}">
                                            {{ $rev ? 'Ver revisión' : 'Revisar' }}
                                        </a>
                                    @elseif (!$rev)
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td>{{ $pase->version_programacion }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($cancelada)
            <p class="text-muted small mb-0">La sesión está cancelada: sus pases de lista no se pueden programar.</p>
        @else
            <details {{ $errors->has('momentos') || $errors->has('momentos.*') || $pases->isEmpty() ? 'open' : '' }}>
                <summary class="btn btn-outline-primary btn-sm">{{ $pases->isEmpty() ? 'Definir los tres momentos' : 'Cambiar las horas' }}</summary>

                <form action="{{ route('sesiones.pases.programar', $sesion->id) }}" method="POST" class="mt-3">
                    @csrf

                    <p class="text-muted small">El horario de la sesión es de {{ substr($sesion->hora_inicio, 0, 5) }} a {{ substr($sesion->hora_fin, 0, 5) }}. Las tres horas deben estar dentro de ese rango y en orden.</p>

                    @include('sesiones._momentos', ['valores' => $valores])

                    <button type="submit" class="btn btn-primary btn-sm">{{ $pases->isEmpty() ? 'Crear pases de lista' : 'Guardar horas' }}</button>
                </form>
            </details>
        @endif
    </div>
</div>

{{-- Estimacion de presencia y ausentismo (FN.12) --}}
<div class="card shadow-sm mt-3">
    <div class="card-body">
        <h2 class="h5 mb-1">Estimación de presencia y ausentismo</h2>
        <p class="text-muted small">
            Se calcula con los tres conteos que validaste: media = (inicio + desarrollo + cierre) ÷ 3 y
            ausentismo = (inscritos − media) ÷ inscritos × 100. Resume la presencia observada en tres momentos;
            no cuenta alumnos únicos ni identifica quién asistió.
        </p>

        @if ($errors->has('estimacion'))
            <div class="alert alert-warning small">
                <strong>Todavía no se puede calcular:</strong>
                <ul class="mb-0">
                    @foreach ($errors->get('estimacion') as $mensaje)
                        <li>{{ $mensaje }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($estimacion)
            @if (!$estimacionVigente)
                <div class="alert alert-warning small">
                    Los conteos validados cambiaron después de este cálculo. Vuelve a calcular para actualizarlo.
                </div>
            @endif

            <div class="row text-center mb-3">
                <div class="col-3">
                    <div class="fs-4 fw-semibold">{{ $estimacion->conteo_inicio }}</div>
                    <div class="small text-muted">Inicio</div>
                </div>
                <div class="col-3">
                    <div class="fs-4 fw-semibold">{{ $estimacion->conteo_desarrollo }}</div>
                    <div class="small text-muted">Desarrollo</div>
                </div>
                <div class="col-3">
                    <div class="fs-4 fw-semibold">{{ $estimacion->conteo_cierre }}</div>
                    <div class="small text-muted">Cierre</div>
                </div>
                <div class="col-3">
                    <div class="fs-4 fw-semibold">{{ $estimacion->inscritos }}</div>
                    <div class="small text-muted">Inscritos</div>
                </div>
            </div>

            <div class="row text-center mb-2">
                <div class="col-6">
                    <div class="fs-3 fw-semibold">{{ number_format((float) $estimacion->media, 2) }}</div>
                    <div class="small text-muted">Presencia media (personas)</div>
                </div>
                <div class="col-6">
                    <div class="fs-3 fw-semibold {{ $estimacionVigente ? '' : 'text-muted' }}">{{ number_format((float) $estimacion->ausentismo, 2) }} %</div>
                    <div class="small text-muted">Ausentismo estimado</div>
                </div>
            </div>

            <p class="small text-muted mb-3">
                Calculado por {{ $estimacion->calculadoPor->nombre ?? '—' }}
                el {{ $estimacion->created_at->timezone(config('app.timezone_local', 'America/Mexico_City'))->format('d/m/Y H:i') }} (hora de México).
            </p>
        @else
            <p class="text-muted small">Aún no hay una estimación para esta sesión.</p>
        @endif

        @if (!$cancelada)
            <form action="{{ route('sesiones.estimacion', $sesion->id) }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary btn-sm">{{ $estimacion ? 'Recalcular estimación' : 'Calcular estimación' }}</button>
            </form>
        @endif
    </div>
</div>
@endsection
