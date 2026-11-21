{{-- Estado de un analisis que aun no termina. Variable esperada: $estado ('pendiente' o 'en_proceso') --}}
@if ($estado === 'pendiente')
    <span class="badge text-bg-warning d-inline-flex align-items-center gap-1">
        <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> En espera
    </span>
@else
    <span class="badge text-bg-warning d-inline-flex align-items-center gap-1" role="status">
        <span class="spinner-border spinner-border-sm" style="--bs-spinner-width: .8rem; --bs-spinner-height: .8rem; --bs-spinner-border-width: .15em;" aria-hidden="true"></span>
        Analizando…
    </span>
@endif
