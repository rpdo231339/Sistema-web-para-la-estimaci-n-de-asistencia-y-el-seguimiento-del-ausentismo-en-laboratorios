<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notificacion extends Model
{
    protected $table = 'notificaciones';

    protected $fillable = [
        'usuario_id',
        'pase_lista_id',
        'tipo',
        'clave_evento',
        'titulo',
        'mensaje',
        'datos',
        'programada_en',
        'vigente',
        'leida_en',
        'estado_correo',
        'intentos_correo',
        'correo_enviado_en',
        'ultimo_error',
    ];

    protected function casts(): array
    {
        return [
            'datos' => 'array',
            'vigente' => 'boolean',
            'programada_en' => 'datetime',
            'leida_en' => 'datetime',
            'correo_enviado_en' => 'datetime',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function pase()
    {
        return $this->belongsTo(PaseLista::class, 'pase_lista_id');
    }
}
