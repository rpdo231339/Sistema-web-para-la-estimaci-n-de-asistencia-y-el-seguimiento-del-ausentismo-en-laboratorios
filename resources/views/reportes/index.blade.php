@extends('layouts.app')

@section('titulo', 'Reportes')

@section('content')
<h1 class="h3 mb-3">Reportes</h1>

<p class="text-muted">Cada reporte respeta tus permisos y se puede descargar en PDF o CSV con los mismos filtros que ves en pantalla.</p>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5"><i class="fa-solid fa-table-list"></i> Reporte por clase</h2>
                <p class="text-muted small">Asistencia confirmada, ausencias justificadas y faltas por clase, con sus porcentajes.</p>
                <a href="{{ route('reportes.clase') }}" class="btn btn-primary btn-sm">Abrir</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5"><i class="fa-solid fa-clock"></i> Reporte de los tres momentos</h2>
                <p class="text-muted small">Conteos validados de inicio, desarrollo y cierre de cada sesión, y su ausentismo estimado.</p>
                <a href="{{ route('reportes.momentos') }}" class="btn btn-primary btn-sm">Abrir</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5"><i class="fa-solid fa-people-group"></i> Comparativo entre grupos</h2>
                <p class="text-muted small">Asistencia y ausencia de cada grupo por periodo, con gráfica de barras.</p>
                <a href="{{ route('reportes.grupos') }}" class="btn btn-primary btn-sm">Abrir</a>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h2 class="h5"><i class="fa-solid fa-triangle-exclamation"></i> Incidencias y pendientes</h2>
                <p class="text-muted small">Momentos sin evidencia, validaciones pendientes, errores de análisis y asistencias sin confirmar.</p>
                <a href="{{ route('reportes.incidencias') }}" class="btn btn-primary btn-sm">Abrir</a>
            </div>
        </div>
    </div>
</div>
@endsection
