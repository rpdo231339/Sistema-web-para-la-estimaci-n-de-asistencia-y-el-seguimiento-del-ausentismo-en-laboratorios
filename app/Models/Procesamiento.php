<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procesamiento extends Model
{
    protected $table = 'procesamientos';

    protected $fillable = [
        'evidencia_id',
        'configuracion_id',
        'solicitado_por',
        'clave_operacion',
        'estado',
        'conteo',
        'datos_resultado',
        'iniciado_en',
        'terminado_en',
        'duracion_ms',
        'error',
        'intentos',
    ];

    protected function casts(): array
    {
        return [
            'datos_resultado' => 'array',
            'iniciado_en' => 'datetime',
            'terminado_en' => 'datetime',
        ];
    }

    public function evidencia()
    {
        return $this->belongsTo(Evidencia::class, 'evidencia_id');
    }

    public function configuracion()
    {
        return $this->belongsTo(ConfiguracionModelo::class, 'configuracion_id');
    }

    // Historial de revisiones docentes, de la mas reciente a la mas antigua
    public function revisiones()
    {
        return $this->hasMany(RevisionConteo::class, 'procesamiento_id')->orderByDesc('id');
    }

    // La revision mas reciente determina la aprobacion de la ejecucion
    public function revisionActual()
    {
        return $this->hasOne(RevisionConteo::class, 'procesamiento_id')->latestOfMany();
    }

    public function solicitadoPor()
    {
        return $this->belongsTo(Usuario::class, 'solicitado_por');
    }
}
