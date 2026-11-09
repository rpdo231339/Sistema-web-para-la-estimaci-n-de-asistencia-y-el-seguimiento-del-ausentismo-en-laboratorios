<?php

namespace App\Console\Commands;

use App\Services\NotificacionService;
use Illuminate\Console\Command;

class ProcesarNotificaciones extends Command
{
    protected $signature = 'notificaciones:procesar';

    protected $description = 'Genera los recordatorios próximos y envía por correo las notificaciones pendientes';

    public function handle(NotificacionService $servicio): int
    {
        $nuevas = $servicio->generarRecordatorios();
        $envio = $servicio->enviarPendientes();

        $this->info("Recordatorios nuevos: {$nuevas}. Correos enviados: {$envio['enviado']}, con error: {$envio['error']}, caducados: {$envio['caducado']}.");

        return self::SUCCESS;
    }
}
