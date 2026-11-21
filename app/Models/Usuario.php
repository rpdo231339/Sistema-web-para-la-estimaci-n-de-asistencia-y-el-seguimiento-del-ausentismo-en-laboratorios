<?php
 
namespace App\Models;
 
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
 
class Usuario extends Authenticatable
{
    use Notifiable;
 
    protected $table = 'usuarios';
 
    protected $fillable = [
        'nombre',
        'correo',
        'password',
        'rol',
        'activo',
        'version_seguridad',
    ];
 
    protected $hidden = [
        'password',
    ];
 
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
 
    // La tabla no tiene remember_token: el sistema no usa "recordarme"
    public function getRememberTokenName()
    {
        return '';
    }
 
    // Laravel busca "email" por defecto; aqui el dato se llama "correo"
    public function getEmailForPasswordReset()
    {
        return $this->correo;
    }
 
    public function routeNotificationForMail($notification = null)
    {
        return $this->correo;
    }


    public function espacios()
    {
        return $this->belongsToMany(Espacio::class, 'encargado_espacio', 'usuario_id', 'espacio_id')
            ->withPivot('activo')
            ->withTimestamps();
    }


    public function espaciosAsignados()
    {
        return $this->espacios()->wherePivot('activo', 1);
    }

    // Tablas y columnas que guardan una referencia a un usuario. Si alguna tiene filas, el usuario ya
    // tiene historial y solo puede desactivarse (en todas la clave foranea es restrictiva)
    public const RELACIONES = [
        ['clases', 'profesor_id'],
        ['sesiones_clase', 'profesor_id'],
        ['revisiones_conteo', 'profesor_id'],
        ['encargado_espacio', 'usuario_id'],
        ['asistencias', 'confirmado_por'],
        ['evidencias', 'cargado_por'],
        ['procesamientos', 'solicitado_por'],
        ['estimaciones_presencia', 'calculado_por'],
        ['evaluaciones', 'registrado_por'],
        ['respaldos', 'administrador_id'],
        ['notificaciones', 'usuario_id'],
        ['auditoria', 'usuario_id'],
    ];

    // De los ids dados, devuelve los que ya tienen algun registro relacionado
    public static function idsConRelaciones($ids): \Illuminate\Support\Collection
    {
        $usados = collect();

        foreach (self::RELACIONES as [$tabla, $columna]) {
            $usados = $usados->merge(DB::table($tabla)->whereIn($columna, $ids)->distinct()->pluck($columna));
        }

        return $usados->map(fn ($id) => (int) $id)->unique()->values();
    }
}
 