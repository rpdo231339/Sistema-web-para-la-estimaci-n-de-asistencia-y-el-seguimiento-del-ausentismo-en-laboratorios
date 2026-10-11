@extends('layouts.app')

@section('titulo', 'Cambiar contraseña')

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">

        <h1 class="h3 mb-3">Cambiar contraseña</h1>

        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('cuenta.password.update') }}" method="POST" id="form-password">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label">Contraseña actual</label>
                        <input type="password" name="password_actual" class="form-control @error('password_actual') is-invalid @enderror">
                        @error('password_actual')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nueva contraseña</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                        @error('password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirmar nueva contraseña</label>
                        <input type="password" name="password_confirmation" class="form-control">
                        <div id="aviso-confirmacion" class="text-danger small mt-1 d-none"></div>
                    </div>

                    <p class="text-muted small">Al cambiar tu contraseña se cerrarán tus sesiones abiertas en otros dispositivos.</p>

                    <button type="submit" class="btn btn-primary">Actualizar</button>
                    <a href="{{ route('panel') }}" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>

    </div>
</div>

<script>
    // Validacion en cliente: longitud y confirmacion (el servidor la repite)
    document.getElementById('form-password').addEventListener('submit', function (evento) {
        let clave = this.querySelector('[name=password]').value;
        let confirmacion = this.querySelector('[name=password_confirmation]').value;
        let aviso = document.getElementById('aviso-confirmacion');

        aviso.classList.add('d-none');

        if (clave.length < 8) {
            aviso.textContent = 'La contraseña debe tener al menos 8 caracteres.';
        } else if (clave !== confirmacion) {
            aviso.textContent = 'La confirmación no coincide con la nueva contraseña.';
        } else {
            return;
        }

        evento.preventDefault();
        aviso.classList.remove('d-none');
    });
</script>
@endsection
