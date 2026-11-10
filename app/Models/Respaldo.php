<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Respaldo extends Model
{
    protected $table = 'respaldos';

    protected $fillable = [
        'administrador_id',
        'operacion',
        'estado',
        'respaldo_origen_id',
        'respaldo_preventivo_id',
        'ruta',
        'bytes',
        'sha256',
        'manifiesto',
        'detalle',
        'iniciado_en',
        'terminado_en',
    ];

    protected function casts(): array
    {
        return [
            'manifiesto' => 'array',
            'iniciado_en' => 'datetime',
            'terminado_en' => 'datetime',
        ];
    }

    public function administrador()
    {
        return $this->belongsTo(Usuario::class, 'administrador_id');
    }

    public function origen()
    {
        return $this->belongsTo(Respaldo::class, 'respaldo_origen_id');
    }

    public function preventivo()
    {
        return $this->belongsTo(Respaldo::class, 'respaldo_preventivo_id');
    }

    public function enCurso(): bool
    {
        return in_array($this->estado, ['pendiente', 'en_proceso'], true);
    }
}
