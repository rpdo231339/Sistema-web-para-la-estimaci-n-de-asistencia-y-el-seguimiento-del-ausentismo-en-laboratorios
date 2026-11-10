@extends('layouts.app')

@section('titulo', 'Respaldos')

@section('content')
@php
    $zona = config('app.timezone_local', 'America/Mexico_City');
    $colores = ['pendiente' => 'secondary', 'en_proceso' => 'warning', 'completado' => 'success', 'error' => 'danger'];
    $textos = ['pendiente' => 'En espera', 'en_proceso' => 'En proceso', 'completado' => 'Completado', 'error' => 'Con error'];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Respaldos y restauración</h1>

    <form action="{{ route('respaldos.store') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-primary" {{ $hayEnCurso ? 'disabled' : '' }}>
            <i class="fa-solid fa-database"></i> Generar copia ahora
        </button>
    </form>
</div>

<div class="alert alert-info small">
    Cada copia incluye la base de datos y los archivos (evidencias, evaluaciones y resultados). Se guardan en un almacenamiento
    protegido del servidor. Las copias contienen datos personales y contraseñas cifradas: descárgalas solo si es necesario y guárdalas con cuidado.
    @if ($hayEnCurso)
        <strong>Hay una operación en curso.</strong> Recarga la página para ver el avance (el worker de colas debe estar encendido).
    @endif
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>Operación</th>
                    <th>Estado</th>
                    <th>Inicio</th>
                    <th>Tamaño</th>
                    <th>Detalle</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($respaldos as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td>
                            {{ $r->operacion === 'respaldo' ? 'Copia' : 'Restauración' }}
                            @if ($r->origen)
                                <div class="small text-muted">de la copia #{{ $r->origen->id }}</div>
                            @endif
                            <div class="small text-muted">{{ optional($r->administrador)->nombre ?? '—' }}</div>
                        </td>
                        <td><span class="badge text-bg-{{ $colores[$r->estado] }}">{{ $textos[$r->estado] }}</span></td>
                        <td>{{ $r->iniciado_en->timezone($zona)->format('d/m/Y H:i') }}</td>
                        <td>{{ $r->bytes ? number_format($r->bytes / 1048576, 2) . ' MB' : '—' }}</td>
                        <td class="small">
                            {{ $r->detalle ?? '—' }}
                            @if ($r->sha256)
                                <div class="text-muted text-break">SHA-256: {{ substr($r->sha256, 0, 16) }}…</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($r->operacion === 'respaldo' && $r->estado === 'completado')
                                <a href="{{ route('respaldos.descargar', $r->id) }}" class="btn btn-outline-secondary btn-sm">Descargar</a>
                                <a href="{{ route('respaldos.confirmar', $r->id) }}" class="btn btn-warning btn-sm {{ $hayEnCurso ? 'disabled' : '' }}">Restaurar</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Todavía no hay copias</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">
    {{ $respaldos->links() }}
</div>
@endsection
