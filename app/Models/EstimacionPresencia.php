<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstimacionPresencia extends Model
{
    protected $table = 'estimaciones_presencia';

    // Historial inmutable: solo existe created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'sesion_id',
        'calculado_por',
        'inscritos',
        'conteo_inicio',
        'conteo_desarrollo',
        'conteo_cierre',
        'media',
        'ausentismo',
        'origen',
    ];

    protected function casts(): array
    {
        return [
            'origen' => 'array',
        ];
    }

    public function sesion()
    {
        return $this->belongsTo(Sesion::class, 'sesion_id');
    }

    public function calculadoPor()
    {
        return $this->belongsTo(Usuario::class, 'calculado_por');
    }

    // Identificadores de las revisiones docentes que dieron origen al calculo, por momento
    public function revisionesOrigen(): array
    {
        return collect($this->origen)->pluck('revision_id', 'momento')->all();
    }
}
