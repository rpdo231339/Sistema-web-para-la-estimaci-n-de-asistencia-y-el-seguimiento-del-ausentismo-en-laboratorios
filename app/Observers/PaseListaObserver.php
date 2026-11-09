<?php

namespace App\Observers;

use App\Models\PaseLista;
use App\Services\NotificacionService;

class PaseListaObserver
{
    public function updated(PaseLista $pase): void
    {
        $servicio = app(NotificacionService::class);

        if ($pase->wasChanged('programado_en')) {
            $servicio->programacionCambiada($pase);
        }

        if ($pase->wasChanged('estado')) {
            if ($pase->estado === 'procesado') {
                $servicio->resultadoListo($pase);
            } elseif ($pase->estado === 'con_incidencia') {
                $servicio->incidenciaEnPase($pase);
            }
        }
    }
}
