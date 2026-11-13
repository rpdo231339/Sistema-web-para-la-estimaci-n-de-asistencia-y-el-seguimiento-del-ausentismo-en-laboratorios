@extends('layouts.app')

@section('titulo', 'Iniciar sesión')

@section('content')
<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body p-4">

                <h1 class="h3 mb-1">Iniciar sesión</h1>
                <p class="text-muted mb-4">Ingresa con tu correo institucional.</p>

                <form action="{{ route('acceso.store') }}" method="POST">
                    @csrf

                    <div class="mb-3">
                        <label for="correo" class="form-label">Correo electrónico</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-envelope"></i></span>
                            <input type="email" id="correo" name="correo" autocomplete="username" class="form-control @error('correo') is-invalid @enderror" value="{{ old('correo') }}" autofocus>
                        </div>
                        @error('correo')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" id="password" name="password" autocomplete="current-password" class="form-control @error('password') is-invalid @enderror">
                        </div>
                        @error('password')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-right-to-bracket"></i> Entrar</button>
                </form>

                <div class="text-center mt-3">
                    <a href="{{ route('recuperacion.solicitud') }}">¿Olvidaste tu contraseña?</a>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
