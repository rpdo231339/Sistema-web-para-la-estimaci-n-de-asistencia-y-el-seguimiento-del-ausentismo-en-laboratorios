@extends('layouts.app')

@section('titulo', 'Notificaciones')

@section('content')
@php
    $zona = config('app.timezone_local', 'America/Mexico_City');
    $etiquetas = [
        'recordatorio' => ['Recordatorio', 'primary'],
        'cambio' => ['Cambio', 'info'],
        'resultado' => ['Resultado', 'success'],
        'incidencia' => ['Incidencia', 'danger'],
    ];
    $correo = [
        'pendiente' => 'Correo pendiente',
        'enviado' => 'Correo enviado',
        'error' => 'Correo con error',
        'incierto' => 'Correo incierto',
        'caducado' => 'Correo no enviado (ya no aplica)',
    ];
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3 mb-0">Notificaciones</h1>

    <form action="{{ route('notificaciones.leerTodas') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-outline-secondary btn-sm">Marcar todas como leídas</button>
    </form>
</div>

<div class="card shadow-sm">
    <div class="list-group list-group-flush">
        @forelse ($notificaciones as $n)
            @php [$texto, $color] = $etiquetas[$n->tipo]; @endphp

            <div class="list-group-item {{ $n->leida_en ? '' : 'bg-light' }}">
                <div class="d-flex justify-content-between align-items-start gap-3">
                    <div>
                        <span class="badge text-bg-{{ $color }}">{{ $texto }}</span>
                        <span class="{{ $n->leida_en ? '' : 'fw-semibold' }}">{{ $n->titulo }}</span>
                        <div class="small mt-1">{{ $n->mensaje }}</div>
                        <div class="small text-muted mt-1">
                            {{ $n->created_at->timezone($zona)->format('d/m/Y H:i') }} · {{ $correo[$n->estado_correo] }}
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-shrink-0">
                        <form action="{{ route('notificaciones.abrir', $n->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">Abrir</button>
                        </form>

                        @if (!$n->leida_en)
                            <form action="{{ route('notificaciones.leer', $n->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-secondary btn-sm">Leída</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="list-group-item text-center text-muted py-4">No tienes notificaciones</div>
        @endforelse
    </div>
</div>

<div class="mt-3">
    {{ $notificaciones->links() }}
</div>
@endsection
