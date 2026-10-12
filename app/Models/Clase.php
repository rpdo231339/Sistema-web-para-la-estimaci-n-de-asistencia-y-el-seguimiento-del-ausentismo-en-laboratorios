<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clase extends Model
{
    protected $table = 'clases';

    protected $fillable = [
        'profesor_id',
        'grupo_id',
        'asignatura',
        'inscritos',
        'observaciones',
        'vigente',
    ];

    protected function casts(): array
    {
        return [
            'vigente' => 'boolean',
        ];
    }

    public function profesor()
    {
        return $this->belongsTo(Usuario::class, 'profesor_id');
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    // Todos los bloques, incluidos los dados de baja
    public function horarios()
    {
        return $this->hasMany(HorarioClase::class, 'clase_id');
    }

    // Solo los bloques vigentes, ordenados por dia y hora
    public function horariosActivos()
    {
        return $this->hasMany(HorarioClase::class, 'clase_id')
            ->where('activo', 1)
            ->orderBy('dia')
            ->orderBy('hora_inicio');
    }
}
