@extends('layouts.app')

@section('titulo', 'Editar clase')

@section('content')
<h1 class="h3 mb-3">Editar clase</h1>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('clases.update', $clase->id) }}" method="POST">
            @csrf
            @method('PUT')

            @include('clases._campos')

            <p class="text-muted small">Los cambios de esta clase no modifican las sesiones que ya se hayan registrado.</p>

            <button type="submit" class="btn btn-primary">Actualizar</button>
            <a href="{{ route('clases.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
