<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sesion extends Model
{
    protected $table = 'sesiones_clase';

    protected $fillable = [
        'clase_id',
        'horario_clase_id',
        'fecha',
        'profesor_id',
        'grupo_id',
        'espacio_id',
        'inscritos',
        'hora_inicio',
        'hora_fin',
        'periodo',
        'contexto_historico',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'contexto_historico' => 'array',
        ];
    }

    public function clase()
    {
        return $this->belongsTo(Clase::class, 'clase_id');
    }

    public function horario()
    {
        return $this->belongsTo(HorarioClase::class, 'horario_clase_id');
    }

    public function profesor()
    {
        return $this->belongsTo(Usuario::class, 'profesor_id');
    }

    public function grupo()
    {
        return $this->belongsTo(Grupo::class, 'grupo_id');
    }

    public function espacio()
    {
        return $this->belongsTo(Espacio::class, 'espacio_id');
    }

    // Los tres momentos del pase de lista, en orden cronologico
    public function pases()
    {
        return $this->hasMany(PaseLista::class, 'sesion_id')->orderBy('programado_en');
    }

    // Registro agregado de asistencia (como maximo uno por sesion)
    public function asistencia()
    {
        return $this->hasOne(Asistencia::class, 'sesion_id');
    }
}
