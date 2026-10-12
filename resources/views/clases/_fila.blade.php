<tr class="fila-horario">
    <td style="min-width: 130px;">
        <input type="hidden" name="horarios[{{ $i }}][id]" value="{{ $fila['id'] ?? '' }}">

        <select name="horarios[{{ $i }}][dia]" class="form-select form-select-sm">
            @foreach (\App\Models\HorarioClase::DIAS as $numero => $nombre)
                <option value="{{ $numero }}" {{ (string) ($fila['dia'] ?? '1') === (string) $numero ? 'selected' : '' }}>{{ $nombre }}</option>
            @endforeach
        </select>
    </td>

    <td>
        <input type="time" name="horarios[{{ $i }}][hora_inicio]" class="form-control form-control-sm" value="{{ $fila['hora_inicio'] ?? '' }}">
    </td>

    <td>
        <input type="time" name="horarios[{{ $i }}][hora_fin]" class="form-control form-control-sm" value="{{ $fila['hora_fin'] ?? '' }}">
    </td>

    <td style="min-width: 190px;">
        <select name="horarios[{{ $i }}][espacio_id]" class="form-select form-select-sm">
            <option value="">Selecciona un espacio</option>
            @foreach ($espacios as $espacio)
                <option value="{{ $espacio->id }}" {{ (string) ($fila['espacio_id'] ?? '') === (string) $espacio->id ? 'selected' : '' }}>
                    {{ $espacio->nombre }} ({{ $espacio->tipo }}){{ $espacio->activo ? '' : ' - inactivo' }}
                </option>
            @endforeach
        </select>
    </td>

    <td class="text-end">
        <button type="button" class="btn btn-outline-danger btn-sm btn-quitar-fila">Quitar</button>
    </td>
</tr>
