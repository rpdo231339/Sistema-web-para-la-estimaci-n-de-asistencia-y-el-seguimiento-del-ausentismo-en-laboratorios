<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Incidencias y pendientes</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 3px 4px; vertical-align: top; }
        th { background: #e9ecef; text-align: left; }
        .nota { margin-top: 10px; color: #555; }
    </style>
</head>
<body>
    <h1>Incidencias y pendientes</h1>
    <div class="meta">
        Generado el {{ $generado }} por {{ $usuario }}.
        Periodo: {{ $filtros['periodo'] ?? 'todos' }} ·
        Del {{ !empty($filtros['desde']) ? \Carbon\Carbon::parse($filtros['desde'])->format('d/m/Y') : 'inicio' }}
        al {{ !empty($filtros['hasta']) ? \Carbon\Carbon::parse($filtros['hasta'])->format('d/m/Y') : 'hoy' }} ·
        Grupo: {{ $grupoNombre ?? 'todos' }} · Espacio: {{ $espacioNombre ?? 'todos' }} ·
        Tipo: {{ !empty($filtros['tipo']) ? \App\Services\ReporteService::TIPOS[$filtros['tipo']] : 'todos' }}
    </div>

    <p>
        @foreach (\App\Services\ReporteService::TIPOS as $clave => $texto)
            {{ $texto }}: <strong>{{ $datos['resumen'][$clave] }}</strong>@if (!$loop->last) · @endif
        @endforeach
    </p>

    <table>
        <thead>
            <tr>
                <th>Fecha</th><th>Asignatura</th><th>Grupo</th><th>Espacio</th><th>Momento</th>
                <th>Tipo</th><th>Estado</th><th>Detalle</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($datos['filas'] as $f)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($f['fecha'])->format('d/m/Y') }}</td>
                    <td>{{ $f['asignatura'] }}</td><td>{{ $f['grupo'] }}</td><td>{{ $f['espacio'] }}</td>
                    <td>{{ $f['momento'] }}</td><td>{{ $f['tipo_texto'] }}</td><td>{{ $f['estado_texto'] }}</td><td>{{ $f['detalle'] }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No hay incidencias ni pendientes para estos filtros</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="nota">
        Pendiente: algo que falta hacer. Incidencia: un problema ya registrado.
        @if ($datos['truncado']) Se revisaron solo las primeras {{ $datos['limite'] }} sesiones. @endif
    </p>
</body>
</html>
