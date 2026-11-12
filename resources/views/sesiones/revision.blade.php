@extends('layouts.app')

@section('titulo', 'Revisión del conteo')

@section('content')
@php
    $zona = config('app.timezone_local', 'America/Mexico_City');
    $revisiones = $proc->revisiones;
    $actual = $revisiones->first();
    $anotada = data_get($proc->datos_resultado, 'anotada');
    $detecciones = data_get($proc->datos_resultado, 'detecciones', []);
    $cancelada = $sesion->estado === 'cancelada';
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Revisión: {{ \App\Models\PaseLista::MOMENTOS[$pase->momento] }}</h1>

    <a href="{{ route('sesiones.show', $sesion->id) }}" class="btn btn-secondary">Volver a la sesión</a>
</div>

<p class="text-muted">
    {{ data_get($sesion->contexto_historico, 'asignatura', '—') }} ·
    {{ $sesion->fecha->format('d/m/Y') }} ·
    {{ data_get($sesion->contexto_historico, 'espacio.nombre', '—') }} ·
    {{ $sesion->inscritos }} inscritos
</p>

<div class="row g-3">

    {{-- Evidencia y detecciones --}}
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Imagen analizada</h2>

                <p class="small text-muted mb-2">
                    Versión {{ $evidencia->version }} de la evidencia · representa las {{ $evidencia->capturadaLocal()->format('d/m/Y H:i') }} (hora de México).
                </p>

                @if ($anotada)
                    <img src="{{ route('procesamientos.anotada', $proc->id) }}" alt="Imagen con las detecciones dibujadas" class="img-fluid rounded border mb-2">
                    <p class="small mb-0">
                        <a href="{{ route('evidencias.archivo', $evidencia->id) }}" target="_blank" rel="noopener">Ver la imagen original</a>
                        · <a href="{{ route('procesamientos.anotada', $proc->id) }}" target="_blank" rel="noopener">Abrir detecciones a tamaño completo</a>
                    </p>
                @else
                    <a href="{{ route('evidencias.archivo', $evidencia->id) }}" target="_blank" rel="noopener">Ver la imagen original</a>
                    <p class="text-muted small mt-2 mb-0">Esta ejecución no generó imagen con detecciones.</p>
                @endif

                @php
                    $sam = data_get($proc->datos_resultado, 'sam');
                    $textoMotivo = [
                        'mascara_debil' => 'su máscara es muy pequeña para la caja',
                        'solapada' => 'se encima con otra persona',
                        'sin_mascara' => 'SAM 3 no generó máscara',
                    ];
                @endphp

                @if ($sam && ($sam['estado'] ?? null) === 'aplicado')
                    @php $aRevisar = collect($detecciones)->map(fn ($d, $i) => [$i + 1, data_get($d, 'refinamiento')])->filter(fn ($x) => data_get($x[1], 'estado') === 'revisar'); @endphp

                    <div class="alert {{ $aRevisar->isEmpty() ? 'alert-success' : 'alert-warning' }} small mt-3 mb-0">
                        <strong>SAM 3 refinó las {{ count($detecciones) }} detecciones de YOLO.</strong>
                        El conteo es el de YOLO; las máscaras ayudan a separar a las personas.
                        @if ($aRevisar->isEmpty())
                            Todas son consistentes.
                        @else
                            Conviene revisar (marcadas en naranja):
                            <ul class="mb-0 mt-1">
                                @foreach ($aRevisar as [$numero, $ref])
                                    <li>#{{ $numero }}: {{ collect($ref['motivos'])->map(fn ($m) => $textoMotivo[$m] ?? $m)->implode('; ') }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @elseif ($sam)
                    <div class="alert alert-secondary small mt-3 mb-0">
                        SAM 3 no pudo refinar esta imagen ({{ $sam['mensaje'] ?? 'sin detalle' }}).
                        Las cajas y el conteo son solo de YOLO.
                    </div>
                @endif
            </div>
        </div>

        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <h2 class="h5">Detalle de la ejecución</h2>

                <dl class="row small mb-0">
                    <dt class="col-5">Conteo automático</dt>
                    <dd class="col-7"><strong>{{ $proc->conteo }}</strong> persona(s)</dd>

                    <dt class="col-5">Modelo</dt>
                    <dd class="col-7">{{ data_get($proc->datos_resultado, 'modelo.pesos', $proc->configuracion->nombre ?? '—') }}</dd>

                    <dt class="col-5">Detecciones</dt>
                    <dd class="col-7">{{ count($detecciones) }}</dd>

                    @if (count($detecciones) > 0)
                        <dt class="col-5">Confianza</dt>
                        <dd class="col-7">
                            mínima {{ number_format(collect($detecciones)->min('confianza') ?? 0, 2) }}
                            · promedio {{ number_format(collect($detecciones)->avg('confianza') ?? 0, 2) }}
                        </dd>
                    @endif

                    <dt class="col-5">Duración</dt>
                    <dd class="col-7">{{ $proc->duracion_ms }} ms</dd>

                    <dt class="col-5">Procesado</dt>
                    <dd class="col-7">{{ optional($proc->terminado_en)->timezone($zona)->format('d/m/Y H:i') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    {{-- Decision del profesor --}}
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-body">
                <h2 class="h5">Tu decisión</h2>

                @if ($actual)
                    <div class="alert alert-{{ $actual->decision === 'validado' ? 'success' : 'warning' }} py-2 small">
                        Última decisión: <strong>{{ $actual->decision === 'validado' ? 'validado con ' . $actual->conteo_revisado : 'observado' }}</strong>
                        el {{ $actual->created_at->timezone($zona)->format('d/m/Y H:i') }}.
                        Si decides de nuevo, la decisión anterior se conserva en el historial.
                    </div>
                @endif

                @if ($cancelada)
                    <p class="text-muted mb-0">La sesión está cancelada y no admite revisiones.</p>
                @else
                    <form action="{{ route('sesiones.revision.guardar', [$sesion->id, $pase->id]) }}" method="POST">
                        @csrf
                        <input type="hidden" name="procesamiento_id" value="{{ $proc->id }}">

                        <div class="mb-3">
                            <label class="form-label">Decisión</label>

                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision" id="dec-validado" value="validado" {{ old('decision', 'validado') === 'validado' ? 'checked' : '' }}>
                                <label class="form-check-label" for="dec-validado">Validar el conteo</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision" id="dec-observado" value="observado" {{ old('decision') === 'observado' ? 'checked' : '' }}>
                                <label class="form-check-label" for="dec-observado">Observar el resultado (no confío en él)</label>
                            </div>

                            @error('decision')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3" id="bloque-conteo">
                            <label class="form-label" for="conteo_revisado">Conteo que validas</label>
                            <input type="number" min="0" step="1" name="conteo_revisado" id="conteo_revisado"
                                   class="form-control @error('conteo_revisado') is-invalid @enderror"
                                   value="{{ old('conteo_revisado', $actual && $actual->decision === 'validado' ? $actual->conteo_revisado : $proc->conteo) }}">
                            <div class="form-text">
                                El conteo automático es {{ $proc->conteo }}. Los inscritos de la sesión son {{ $sesion->inscritos }}.
                                Si lo cambias, el motivo es obligatorio.
                            </div>
                            @error('conteo_revisado')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="motivo">Motivo <span class="text-muted small" id="motivo-ayuda">(obligatorio si corriges u observas)</span></label>
                            <textarea name="motivo" id="motivo" rows="3" class="form-control @error('motivo') is-invalid @enderror">{{ old('motivo') }}</textarea>
                            @error('motivo')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-success" onclick="return confirm('¿Registrar esta decisión?');">Registrar decisión</button>
                    </form>

                    <script>
                        // Muestra u oculta el conteo segun la decision elegida
                        (function () {
                            const radios = document.querySelectorAll('input[name="decision"]');
                            const bloque = document.getElementById('bloque-conteo');

                            function actualizar() {
                                const valor = document.querySelector('input[name="decision"]:checked');
                                bloque.style.display = (valor && valor.value === 'observado') ? 'none' : '';
                            }

                            radios.forEach(function (r) { r.addEventListener('change', actualizar); });
                            actualizar();
                        })();
                    </script>
                @endif
            </div>
        </div>

        {{-- Historial inmutable --}}
        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <h2 class="h5">Historial de revisiones</h2>

                @forelse ($revisiones as $r)
                    <div class="border-bottom pb-2 mb-2 small">
                        <span class="badge text-bg-{{ $r->decision === 'validado' ? 'success' : 'warning' }}">{{ $r->decision === 'validado' ? 'Validado' : 'Observado' }}</span>
                        @if ($r->decision === 'validado')
                            conteo {{ $r->conteo_revisado }}
                            @if ((int) $r->conteo_revisado !== (int) $proc->conteo)
                                <span class="text-muted">(corregido desde {{ $proc->conteo }})</span>
                            @endif
                        @endif
                        <span class="text-muted d-block">{{ $r->profesor->nombre ?? '—' }} · {{ $r->created_at->timezone($zona)->format('d/m/Y H:i') }}</span>
                        @if ($r->motivo)
                            <span class="d-block" style="white-space: pre-line;">{{ $r->motivo }}</span>
                        @endif
                    </div>
                @empty
                    <p class="text-muted small mb-0">Todavía no hay revisiones de este resultado.</p>
                @endforelse
            </div>
        </div>
    </div>

</div>
@endsection
