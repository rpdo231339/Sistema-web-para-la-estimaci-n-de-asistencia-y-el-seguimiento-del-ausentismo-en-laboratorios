<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecuperarContrasenaCorreo extends Mailable
{
    use Queueable, SerializesModels;

    public $usuario;
    public $url;
    public $minutos;

    public function __construct($usuario, $token)
    {
        $this->usuario = $usuario;
        $this->url = route('recuperacion.formulario', [
            'token' => $token,
            'correo' => $usuario->correo,
        ]);

        // Minutos de vigencia del enlace (config/auth.php, passwords.users.expire)
        $this->minutos = config('auth.passwords.users.expire');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recuperación de acceso',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.recuperar_contrasena',
        );
    }
}
