<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de los tres momentos</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 3px 4px; }
        th { background: #e9ecef; text-align: left; }
        .n { text-align: right; }
        .vacio { color: #777; font-style: italic; }
        .nota { margin-top: 10px; color: #555; }
    </style>
</head>
<body>
    <h1>Reporte de los tres momentos</h1>
    <div class="meta">
        Generado el {{ $generado }} por {{ $usuario }}.
        Periodo: {{ $filtros['periodo'] ?? 'todos' }} ·
        Del {{ !empty($filtros['desde']) ? \Carbon\Carbon::parse($filtros['desde'])->format('d/m/Y') : 'inicio' }}
        al {{ !empty($filtros['hasta']) ? \Carbon\Carbon::parse($filtros['hasta'])->format('d/m/Y') : 'hoy' }} ·
        Grupo: {{ $grupoNombre ?? 'todos' }} · Espacio: {{ $espacioNombre ?? 'todos' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha</th><th>Asignatura</th><th>Grupo</th><th>Espacio</th><th class="n">Inscritos</th>
                <th class="n">Inicio</th><th class="n">Desarrollo</th><th class="n">Cierre</th>
                <th class="n">Media</th><th class="n">Ausentismo est.</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($datos['filas'] as $f)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($f['fecha'])->format('d/m/Y') }} {{ $f['horario'] }}</td>
                    <td>{{ $f['asignatura'] }}</td><td>{{ $f['grupo'] }}</td><td>{{ $f['espacio'] }}</td>
                    <td class="n">{{ $f['inscritos'] }}</td>
                    @foreach (['inicio', 'desarrollo', 'cierre'] as $m)
                        <td class="n {{ $f['momentos'][$m]['tipo'] === 'validado' ? '' : 'vacio' }}">{{ $f['momentos'][$m]['texto'] }}</td>
                    @endforeach
                    <td class="n">{{ $f['media'] ?? '—' }}</td>
                    <td class="n">{{ $f['estimacion'] === 'vigente' ? $f['ausentismo'] . ' %' : ($f['estimacion'] === 'desactualizada' ? 'Desactualizada' : '—') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="nota">
        {{ $datos['sesiones'] }} sesión(es), {{ $datos['completas'] }} con los tres momentos validados.
        «Sin evidencia» significa que no hay imagen para ese momento; no es un conteo de cero.
        @if ($datos['truncado']) Se muestran solo las primeras {{ $datos['limite'] }} sesiones. @endif
    </p>
</body>
</html>
