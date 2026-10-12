<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Espacio extends Model
{
    protected $table = 'espacios';

    protected $fillable = [
        'nombre',
        'tipo',
        'ubicacion',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    // Encargados vinculados al espacio (incluye asignaciones dadas de baja)
    public function encargados()
    {
        return $this->belongsToMany(Usuario::class, 'encargado_espacio', 'espacio_id', 'usuario_id')
            ->withPivot('activo')
            ->withTimestamps();
    }

    // Solo las asignaciones vigentes
    public function encargadosActivos()
    {
        return $this->encargados()->wherePivot('activo', 1);
    }

    // Bloques de clase que usan este espacio
    public function horarios()
    {
        return $this->hasMany(HorarioClase::class, 'espacio_id');
    }
}
