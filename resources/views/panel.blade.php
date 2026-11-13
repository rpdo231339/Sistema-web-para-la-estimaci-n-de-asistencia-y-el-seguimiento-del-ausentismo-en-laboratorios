@extends('layouts.app')

@section('titulo', 'Inicio')

@section('content')
@php
    // Cada estado lleva color, icono y texto: el color nunca es la unica senal
    $etiquetasEstado = [
        'sin_evidencia' => ['Sin evidencia', 'fa-circle-minus'],
        'procesado' => ['Procesado', 'fa-gears'],
        'validado' => ['Validado', 'fa-circle-check'],
        'con_incidencia' => ['Con incidencia', 'fa-triangle-exclamation'],
    ];
    $totalPases = array_sum($estados);
@endphp

<div class="ue-saludo">
    <h1 class="h3 mb-1">{{ $saludo }}, {{ \Illuminate\Support\Str::words(Auth::user()->nombre, 1, '') }}</h1>
    <div class="fecha">{{ $fecha }}</div>
</div>

{{-- Indicadores: maximo cuatro --}}
<div class="row g-3 mb-4">
    @foreach ($kpis as $kpi)
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="{{ $kpi['url'] }}" class="card shadow-sm ue-kpi">
                <span class="ue-kpi-icono {{ $kpi['alerta'] ? 'es-alerta' : $kpi['tono'] }}" aria-hidden="true">
                    <i class="fa-solid {{ $kpi['icono'] }}"></i>
                </span>
                <span>
                    <span class="ue-kpi-valor d-block">{{ $kpi['valor'] }}</span>
                    <span class="ue-kpi-etiqueta d-block">{{ $kpi['etiqueta'] }}</span>
                    @if ($kpi['nota'])
                        <span class="ue-kpi-nota d-block"><i class="fa-solid fa-circle-exclamation"></i> {{ $kpi['nota'] }}</span>
                    @endif
                </span>
            </a>
        </div>
    @endforeach
</div>

<div class="row g-3">
    {{-- Listas cortas segun el rol --}}
    <div class="col-lg-8">
        <div class="row g-3">
            @foreach ($listas as $lista)
                <div class="col-12 {{ count($listas) > 1 ? 'col-md-6' : '' }}">
                    <div class="card shadow-sm h-100">
                        <div class="card-body">
                            <h2 class="ue-seccion-titulo h6"><i class="fa-solid {{ $lista['icono'] }}"></i> {{ $lista['titulo'] }}</h2>

                            @if (count($lista['items']) > 0)
                                <ul class="ue-lista">
                                    @foreach ($lista['items'] as $item)
                                        <li>
                                            <span class="ue-hora">{{ $item['hora'] }}</span>
                                            <span class="flex-grow-1">
                                                <span class="principal d-block">{{ $item['titulo'] }}</span>
                                                <span class="secundario d-block">{{ $item['detalle'] }}</span>
                                            </span>
                                            @if ($item['url'])
                                                <a href="{{ $item['url'] }}" class="btn btn-secondary btn-sm" aria-label="Abrir {{ $item['titulo'] }}">
                                                    Abrir <i class="fa-solid fa-arrow-right"></i>
                                                </a>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="ue-vacio"><i class="fa-regular fa-calendar-check"></i>{{ $lista['vacio'] }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- Estado de los pases de los ultimos 7 dias --}}
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="ue-seccion-titulo h6"><i class="fa-solid fa-chart-simple"></i> Pases de lista de los últimos 7 días</h2>

                        @if ($totalPases > 0)
                            <div class="ue-barra" role="img"
                                 aria-label="{{ collect($etiquetasEstado)->map(fn ($e, $clave) => ($estados[$clave] ?? 0) . ' ' . strtolower($e[0]))->implode(', ') }}">
                                @foreach ($etiquetasEstado as $clave => $etiqueta)
                                    @if (($estados[$clave] ?? 0) > 0)
                                        <span class="e-{{ $clave }}" style="width: {{ round(($estados[$clave] / $totalPases) * 100, 2) }}%"></span>
                                    @endif
                                @endforeach
                            </div>

                            <ul class="ue-leyenda">
                                @foreach ($etiquetasEstado as $clave => $etiqueta)
                                    <li>
                                        <i class="fa-solid {{ $etiqueta[1] }} i-{{ $clave }}" aria-hidden="true"></i>
                                        {{ $etiqueta[0] }}
                                        <span class="cantidad">{{ $estados[$clave] ?? 0 }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <div class="ue-vacio"><i class="fa-regular fa-chart-bar"></i>Todavía no hay pases de lista en los últimos 7 días.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Columna lateral: accesos y estado del sistema --}}
    <div class="col-lg-4">
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h2 class="ue-seccion-titulo h6"><i class="fa-solid fa-bolt"></i> Accesos rápidos</h2>
                <div class="d-grid gap-2">
                    @foreach ($accesos as [$texto, $url, $icono, $clase])
                        <a href="{{ $url }}" class="btn {{ $clase }}"><i class="fa-solid {{ $icono }}"></i> {{ $texto }}</a>
                    @endforeach
                </div>
            </div>
        </div>

        @if ($sistema)
            @php $respaldo = $sistema['respaldo']; @endphp

            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="ue-seccion-titulo h6"><i class="fa-solid fa-server"></i> Estado del sistema</h2>

                    <div class="ue-estado-sistema">
                        <div class="fila">
                            <span><i class="fa-solid fa-database me-1"></i> Último respaldo</span>
                            @if ($respaldo)
                                @if ($respaldo->estado === 'completado')
                                    <span class="badge text-bg-success"><i class="fa-solid fa-circle-check"></i> {{ $respaldo->iniciado_en->timezone($sistema['zona'])->format('d/m/Y H:i') }}</span>
                                @elseif ($respaldo->estado === 'error')
                                    <span class="badge text-bg-danger"><i class="fa-solid fa-circle-xmark"></i> Con error</span>
                                @else
                                    <span class="badge text-bg-secondary"><i class="fa-solid fa-hourglass-half"></i> En curso</span>
                                @endif
                            @else
                                <span class="badge text-bg-warning"><i class="fa-solid fa-triangle-exclamation"></i> Nunca</span>
                            @endif
                        </div>

                        <div class="fila">
                            <span><i class="fa-solid fa-gears me-1"></i> Análisis en curso</span>
                            <span class="badge text-bg-secondary">{{ $sistema['en_curso'] }}</span>
                        </div>

                        <div class="fila">
                            <span><i class="fa-solid fa-circle-xmark me-1"></i> Análisis con error (7 días)</span>
                            @if ($sistema['errores'] > 0)
                                <span class="badge text-bg-danger"><i class="fa-solid fa-triangle-exclamation"></i> {{ $sistema['errores'] }}</span>
                            @else
                                <span class="badge text-bg-success"><i class="fa-solid fa-circle-check"></i> 0</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
