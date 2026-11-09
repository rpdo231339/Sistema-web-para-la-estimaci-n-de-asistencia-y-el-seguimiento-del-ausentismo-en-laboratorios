<?php

namespace App\Mail;

use App\Models\Notificacion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificacionCorreo extends Mailable
{
    use Queueable, SerializesModels;

    public $notificacion;
    public $url;

    public function __construct(Notificacion $notificacion)
    {
        $this->notificacion = $notificacion;

        // Solo rutas internas (empiezan con una sola barra)
        $ruta = $notificacion->datos['ruta'] ?? null;
        $this->url = (is_string($ruta) && str_starts_with($ruta, '/') && !str_starts_with($ruta, '//')) ? url($ruta) : null;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notificacion->titulo,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notificacion',
        );
    }
}
