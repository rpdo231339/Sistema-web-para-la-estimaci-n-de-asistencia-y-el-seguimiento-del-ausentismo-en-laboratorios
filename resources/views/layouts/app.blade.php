<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Inicio') | Asistencia</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
</head>
<body>

    <a href="#contenido" class="ue-saltar">Saltar al contenido</a>

    @auth
        @php
            $usuarioActual = Auth::user();

            // Opciones del menu por rol: [texto, ruta, icono, patron de ruta activa]
            $menu = match ($usuarioActual->rol) {
                'administrador' => [
                    ['Usuarios', 'usuarios.index', 'fa-users', 'usuarios.*'],
                    ['Grupos', 'grupos.index', 'fa-people-group', 'grupos.*'],
                    ['Espacios', 'espacios.index', 'fa-building', 'espacios.*'],
                    ['Evaluaciones', 'evaluaciones.index', 'fa-flask', 'evaluaciones.*'],
                    ['Reportes', 'reportes.index', 'fa-chart-column', 'reportes.*'],
                    ['Respaldos', 'respaldos.index', 'fa-database', 'respaldos.*'],
                ],
                'encargado' => [
                    ['Pases de lista', 'pases.index', 'fa-camera', 'pases.*'],
                    ['Espacios', 'espacios.index', 'fa-building', 'espacios.*'],
                    ['Reportes', 'reportes.index', 'fa-chart-column', 'reportes.*'],
                ],
                default => [
                    ['Mis clases', 'clases.index', 'fa-chalkboard-user', 'clases.*'],
                    ['Mis sesiones', 'sesiones.index', 'fa-calendar-check', 'sesiones.*'],
                    ['Reportes', 'reportes.index', 'fa-chart-column', 'reportes.*'],
                ],
            };

            $noLeidas = \App\Models\Notificacion::where('usuario_id', Auth::id())
                ->where('vigente', 1)->where('programada_en', '<=', now())->whereNull('leida_en')->count();
        @endphp

        <nav class="navbar navbar-expand-lg ue-nav" aria-label="Menú principal">
            <div class="container">
                <a class="ue-marca" href="{{ route('panel') }}" aria-label="Ir al inicio">
                    <span class="ue-logo"><img src="{{ asset('img/logo-upemor.png') }}" alt="Upemor, Universidad Politécnica"></span>
                    <span class="ue-marca-texto">Asistencia<small>Aulas y laboratorios</small></span>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal"
                        aria-controls="menuPrincipal" aria-expanded="false" aria-label="Abrir el menú">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="menuPrincipal">
                    <ul class="navbar-nav me-auto mt-3 mt-lg-0">
                        <li class="nav-item">
                            <a href="{{ route('panel') }}" class="nav-link {{ request()->routeIs('panel') ? 'active' : '' }}" @if (request()->routeIs('panel')) aria-current="page" @endif>
                                <i class="fa-solid fa-house"></i> Inicio
                            </a>
                        </li>

                        @foreach ($menu as [$texto, $ruta, $icono, $patron])
                            <li class="nav-item">
                                <a href="{{ route($ruta) }}" class="nav-link {{ request()->routeIs($patron) ? 'active' : '' }}" @if (request()->routeIs($patron)) aria-current="page" @endif>
                                    <i class="fa-solid {{ $icono }}"></i> {{ $texto }}
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                        <a href="{{ route('notificaciones.index') }}"
                           class="nav-link {{ request()->routeIs('notificaciones.*') ? 'active' : '' }}"
                           aria-label="Notificaciones{{ $noLeidas > 0 ? ', ' . $noLeidas . ' sin leer' : '' }}">
                            <i class="fa-solid fa-bell"></i>
                            <span class="d-lg-none">Notificaciones</span>
                            @if ($noLeidas > 0)
                                <span class="ue-campana-contador">{{ $noLeidas }}</span>
                            @endif
                        </a>

                        <div class="dropdown">
                            <button class="ue-usuario dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-circle-user"></i>
                                <span>{{ \Illuminate\Support\Str::words($usuarioActual->nombre, 2, '') }}</span>
                            </button>

                            <ul class="dropdown-menu dropdown-menu-end">
                                <li class="px-3 py-2">
                                    <div class="fw-semibold">{{ $usuarioActual->nombre }}</div>
                                    <div class="small text-muted text-capitalize">{{ $usuarioActual->rol }}</div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item" href="{{ route('cuenta.password') }}">
                                        <i class="fa-solid fa-key"></i> Cambiar contraseña
                                    </a>
                                </li>
                                <li>
                                    <form action="{{ route('salir') }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="dropdown-item">
                                            <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    @else
        <header class="ue-visitante">
            <div class="container">
                <span class="ue-marca mb-0">
                    <span class="ue-logo"><img src="{{ asset('img/logo-upemor.png') }}" alt="Upemor, Universidad Politécnica"></span>
                    <span class="ue-marca-texto">Asistencia<small>Aulas y laboratorios</small></span>
                </span>
            </div>
        </header>
    @endauth

    <main id="contenido" class="container pt-4 pb-5">
        @include('partials.alerts')

        @yield('content')
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
