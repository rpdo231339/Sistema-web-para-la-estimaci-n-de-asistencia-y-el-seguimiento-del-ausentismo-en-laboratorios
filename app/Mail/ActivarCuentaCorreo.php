<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ActivarCuentaCorreo extends Mailable
{
    use Queueable, SerializesModels;

    public $usuario;
    public $url;
    public $horas;

    public function __construct($usuario, $token)
    {
        $this->usuario = $usuario;
        $this->url = route('activacion.formulario', [
            'token' => $token,
            'correo' => $usuario->correo,
        ]);

        // Vigencia del enlace en horas (config/auth.php, passwords.activaciones.expire, en minutos)
        $this->horas = (int) round(config('auth.passwords.activaciones.expire') / 60);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Activa tu cuenta',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.activar_cuenta',
        );
    }
}
