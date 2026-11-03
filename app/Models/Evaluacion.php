<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Evaluacion extends Model
{
    protected $table = 'evaluaciones';

    protected $fillable = [
        'procesamiento_id',
        'registrado_por',
        'ambiente',
        'iluminacion',
        'angulo',
        'oclusion',
        'referencia_manual',
        'omisiones',
        'duplicaciones',
        'observaciones',
    ];

    public function procesamiento()
    {
        return $this->belongsTo(Procesamiento::class, 'procesamiento_id');
    }

    public function registradoPor()
    {
        return $this->belongsTo(Usuario::class, 'registrado_por');
    }

    // Diferencia con signo: conteo automatico menos referencia manual (solo si hay resultado procesado)
    public function diferencia(): ?int
    {
        $p = $this->procesamiento;

        if (!$p || $p->estado !== 'procesado' || $p->conteo === null) {
            return null;
        }

        return (int) $p->conteo - (int) $this->referencia_manual;
    }
}
