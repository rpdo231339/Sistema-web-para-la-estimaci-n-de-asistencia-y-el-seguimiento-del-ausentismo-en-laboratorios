<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Evidencia extends Model
{
    protected $table = 'evidencias';

    // La tabla solo tiene created_at
    const UPDATED_AT = null;

    protected $fillable = [
        'pase_lista_id',
        'archivo_origen_id',
        'version',
        'tipo',
        'ruta',
        'nombre_original',
        'mime',
        'bytes',
        'sha256',
        'ancho',
        'alto',
        'capturada_en',
        'instante_ms',
        'cargado_por',
    ];

    protected function casts(): array
    {
        return [
            'capturada_en' => 'datetime',
        ];
    }

    public function pase()
    {
        return $this->belongsTo(PaseLista::class, 'pase_lista_id');
    }

    public function cargadoPor()
    {
        return $this->belongsTo(Usuario::class, 'cargado_por');
    }

    public function procesamientos()
    {
        return $this->hasMany(Procesamiento::class, 'evidencia_id');
    }

    // La ejecucion mas reciente determina el resultado actual
    public function procesamientoActual()
    {
        return $this->hasOne(Procesamiento::class, 'evidencia_id')->latestOfMany();
    }

    // Hora que representa la imagen, en la zona institucional
    public function capturadaLocal(): Carbon
    {
        return $this->capturada_en->copy()->timezone(config('app.timezone_local', 'America/Mexico_City'));
    }

    // Momento real en que se cargo el archivo, en la zona institucional
    public function cargadaLocal(): Carbon
    {
        return $this->created_at->copy()->timezone(config('app.timezone_local', 'America/Mexico_City'));
    }

    public function tamanoLegible(): string
    {
        if ($this->bytes >= 1048576) {
            return number_format($this->bytes / 1048576, 1) . ' MB';
        }

        return number_format($this->bytes / 1024, 0) . ' KB';
    }
}
