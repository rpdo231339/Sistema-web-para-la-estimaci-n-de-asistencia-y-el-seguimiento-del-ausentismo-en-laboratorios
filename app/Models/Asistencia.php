<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    protected $table = 'asistencias';

    protected $fillable = [
        'sesion_id',
        'asistentes',
        'justificados',
        'faltas',
        'estado',
        'observaciones',
        'confirmado_por',
        'confirmado_en',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'confirmado_en' => 'datetime',
        ];
    }

    public function sesion()
    {
        return $this->belongsTo(Sesion::class, 'sesion_id');
    }

    public function confirmadoPor()
    {
        return $this->belongsTo(Usuario::class, 'confirmado_por');
    }
}
