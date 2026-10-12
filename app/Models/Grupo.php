<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    protected $table = 'grupos';

    protected $fillable = [
        'clave',
        'programa',
        'cuatrimestre',
        'periodo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function clases()
    {
        return $this->hasMany(Clase::class, 'grupo_id');
    }
}
