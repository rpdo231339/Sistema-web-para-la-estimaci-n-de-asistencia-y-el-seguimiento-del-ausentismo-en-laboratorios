<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevisionConteo extends Model
{
    protected $table = 'revisiones_conteo';

    // El historial es inmutable: solo existe created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'procesamiento_id',
        'profesor_id',
        'decision',
        'conteo_revisado',
        'motivo',
    ];

    public function procesamiento()
    {
        return $this->belongsTo(Procesamiento::class, 'procesamiento_id');
    }

    public function profesor()
    {
        return $this->belongsTo(Usuario::class, 'profesor_id');
    }
}
