{{-- Hora local de los tres momentos del pase de lista. Variable esperada: $valores (inicio, desarrollo, cierre) --}}
<div class="row">
    @foreach (['inicio' => 'Inicio', 'desarrollo' => 'Desarrollo', 'cierre' => 'Cierre'] as $clave => $etiqueta)
        <div class="col-md-4 mb-3">
            <label class="form-label">{{ $etiqueta }}</label>
            <input type="time" name="momentos[{{ $clave }}]" class="form-control campo-momento @error('momentos.' . $clave) is-invalid @enderror" value="{{ old('momentos.' . $clave, $valores[$clave] ?? '') }}">

            @error('momentos.' . $clave)
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>
    @endforeach
</div>

@error('momentos')
    <div class="text-danger small mb-2">{{ $message }}</div>
@enderror
