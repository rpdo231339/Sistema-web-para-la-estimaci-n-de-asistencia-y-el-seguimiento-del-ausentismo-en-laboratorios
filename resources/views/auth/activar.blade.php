@extends('layouts.app')

@section('titulo', 'Activar cuenta')

@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">

                <h1 class="h3 mb-1">Asistencia</h1>
                <h2 class="h6 text-muted mb-4">ACTIVA TU CUENTA</h2>

                <form action="{{ route('activacion.activar') }}" method="POST" id="form-activar">
                    @csrf

                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="correo" value="{{ old('correo', $correo) }}">

                    @error('correo')
                        <div class="text-danger small mb-2">{{ $message }}</div>
                    @enderror

                    <div class="mb-3">
                        <label class="form-label">Cuenta</label>
                        <input type="text" class="form-control" value="{{ old('correo', $correo) }}" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Elige tu contraseña</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                        @error('password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirmar contraseña</label>
                        <input type="password" name="password_confirmation" class="form-control">
                        <div id="aviso-confirmacion" class="text-danger small mt-1 d-none"></div>
                    </div>

                    <button type="submit" class="btn btn-success w-100">Activar cuenta</button>
                </form>

                <div class="text-center mt-3">
                    <a href="{{ route('acceso') }}">Volver al inicio de sesión</a>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
    // Validacion en cliente: longitud y confirmacion (el servidor la repite)
    document.getElementById('form-activar').addEventListener('submit', function (evento) {
        let clave = this.querySelector('[name=password]').value;
        let confirmacion = this.querySelector('[name=password_confirmation]').value;
        let aviso = document.getElementById('aviso-confirmacion');

        aviso.classList.add('d-none');

        if (clave.length < 8) {
            aviso.textContent = 'La contraseña debe tener al menos 8 caracteres.';
        } else if (clave !== confirmacion) {
            aviso.textContent = 'La confirmación no coincide con la contraseña.';
        } else {
            return;
        }

        evento.preventDefault();
        aviso.classList.remove('d-none');
    });
</script>
@endsection
