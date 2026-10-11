@extends('layouts.app')

@section('titulo', 'Recuperar acceso')

@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">

                <h1 class="h3 mb-1">Asistencia</h1>
                <h2 class="h6 text-muted mb-3">RECUPERAR ACCESO</h2>

                <p class="text-muted small">Escribe tu correo y, si la cuenta existe y está activa, te enviaremos un enlace temporal para elegir una nueva contraseña.</p>

                <form action="{{ route('recuperacion.enviar') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" name="correo" class="form-control @error('correo') is-invalid @enderror" value="{{ old('correo') }}" autofocus>
                        </div>
                        @error('correo')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Enviar enlace</button>
                </form>

                <div class="text-center mt-3">
                    <a href="{{ route('acceso') }}">Volver al inicio de sesión</a>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
