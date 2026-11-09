<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte por clase</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0 0 4px; }
        .meta { color: #555; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #bbb; padding: 3px 4px; }
        th { background: #e9ecef; text-align: left; }
        .n { text-align: right; }
        .total td { font-weight: bold; background: #f4f4f4; }
        .nota { margin-top: 10px; color: #555; }
    </style>
</head>
<body>
    @php $pct = fn ($v) => $v === null ? '—' : $v . ' %'; @endphp

    <h1>Reporte por clase</h1>
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
                <th>Asignatura</th><th>Grupo</th><th>Profesor</th>
                <th class="n">Ses.</th><th class="n">Conf.</th><th class="n">Inscritos</th>
                <th class="n">Asist.</th><th class="n">Justif.</th><th class="n">Faltas</th>
                <th class="n">% asist.</th><th class="n">% ausencia</th><th class="n">% faltas</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($datos['filas'] as $f)
                <tr>
                    <td>{{ $f['asignatura'] }}</td><td>{{ $f['grupo'] }}</td><td>{{ $f['profesor'] }}</td>
                    <td class="n">{{ $f['sesiones'] }}</td><td class="n">{{ $f['confirmadas'] }}</td><td class="n">{{ $f['inscritos'] }}</td>
                    <td class="n">{{ $f['asistentes'] }}</td><td class="n">{{ $f['justificados'] }}</td><td class="n">{{ $f['faltas'] }}</td>
                    <td class="n">{{ $pct($f['asistencia_pct']) }}</td><td class="n">{{ $pct($f['ausencia_pct']) }}</td><td class="n">{{ $pct($f['faltas_pct']) }}</td>
                </tr>
            @endforeach
            @if (count($datos['filas']) > 0)
                @php $t = $datos['total']; @endphp
                <tr class="total">
                    <td colspan="3">TOTAL</td>
                    <td class="n">{{ $t['sesiones'] }}</td><td class="n">{{ $t['confirmadas'] }}</td><td class="n">{{ $t['inscritos'] }}</td>
                    <td class="n">{{ $t['asistentes'] }}</td><td class="n">{{ $t['justificados'] }}</td><td class="n">{{ $t['faltas'] }}</td>
                    <td class="n">{{ $pct($t['asistencia_pct']) }}</td><td class="n">{{ $pct($t['ausencia_pct']) }}</td><td class="n">{{ $pct($t['faltas_pct']) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="nota">
        Solo cuentan las sesiones con asistencia confirmada por el profesor; las canceladas no se incluyen.
        Ausencia = justificados + faltas. Los porcentajes se calculan sobre la suma de inscritos, sin promediar porcentajes.
    </p>
</body>
</html>
