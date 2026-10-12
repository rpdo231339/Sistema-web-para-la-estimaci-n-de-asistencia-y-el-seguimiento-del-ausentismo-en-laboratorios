@extends('layouts.app')

@section('titulo', 'Nueva clase')

@section('content')
<h1 class="h3 mb-3">Registrar clase</h1>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="{{ route('clases.store') }}" method="POST">
            @csrf

            @include('clases._campos')

            <button type="submit" class="btn btn-success">Guardar</button>
            <a href="{{ route('clases.index') }}" class="btn btn-secondary">Cancelar</a>
        </form>
    </div>
</div>
@endsection
