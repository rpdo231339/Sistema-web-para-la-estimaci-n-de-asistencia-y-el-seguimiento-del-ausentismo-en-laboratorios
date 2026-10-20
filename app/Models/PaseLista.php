<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class PaseLista extends Model
{
    protected $table = 'pases_lista';

    public const MOMENTOS = [
        'inicio' => 'Inicio',
        'desarrollo' => 'Desarrollo',
        'cierre' => 'Cierre',
    ];

    protected $fillable = [
        'sesion_id',
        'momento',
        'programado_en',
        'version_programacion',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'programado_en' => 'datetime',
        ];
    }

    public function sesion()
    {
        return $this->belongsTo(Sesion::class, 'sesion_id');
    }

    // Historial completo de versiones, de la mas reciente a la mas antigua
    public function evidencias()
    {
        return $this->hasMany(Evidencia::class, 'pase_lista_id')->orderByDesc('version');
    }

    // La version mas alta es la vigente
    public function evidenciaVigente()
    {
        return $this->hasOne(Evidencia::class, 'pase_lista_id')->latestOfMany('version');
    }

    // El instante se guarda en UTC; aqui se convierte a la zona institucional
    public function programadoLocal(): Carbon
    {
        return $this->programado_en->copy()->timezone(config('app.timezone_local', 'America/Mexico_City'));
    }
}
