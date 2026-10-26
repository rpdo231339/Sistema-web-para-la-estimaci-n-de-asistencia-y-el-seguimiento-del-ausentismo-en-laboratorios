<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionModelo extends Model
{
    protected $table = 'configuraciones_modelo';

    protected $fillable = [
        'nombre',
        'yolo_version',
        'yolo_pesos',
        'sam_version',
        'sam_pesos',
        'huellas_pesos',
        'parametros',
        'entorno',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'huellas_pesos' => 'array',
            'parametros' => 'array',
            'entorno' => 'array',
            'activo' => 'boolean',
        ];
    }
}
