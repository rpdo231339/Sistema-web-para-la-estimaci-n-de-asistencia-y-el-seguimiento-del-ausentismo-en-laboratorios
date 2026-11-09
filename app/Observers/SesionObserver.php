<?php

namespace App\Observers;

use App\Models\Sesion;
use App\Services\NotificacionService;

class SesionObserver
{
    public function updated(Sesion $sesion): void
    {
        if ($sesion->wasChanged('estado') && $sesion->estado === 'cancelada') {
            app(NotificacionService::class)->sesionCancelada($sesion);
        }
    }
}
