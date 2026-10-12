<?php
 
namespace App\Models;
 
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
 
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
}
 