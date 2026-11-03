<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditoria';

    // Bitacora inmutable: solo existe created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'usuario_id',
        'entidad',
        'registro_id',
        'accion',
        'antes',
        'despues',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'antes' => 'array',
            'despues' => 'array',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
