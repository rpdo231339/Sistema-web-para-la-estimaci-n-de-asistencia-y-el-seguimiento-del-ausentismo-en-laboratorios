<?php

namespace App\Jobs;

use App\Models\Respaldo;
use App\Services\RespaldoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class EjecutarRespaldo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Sin reintentos automaticos: una restauracion nunca se repite sola
    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $respaldoId)
    {
    }

    public function handle(RespaldoService $servicio): void
    {
        $respaldo = Respaldo::find($this->respaldoId);

        if (!$respaldo) {
            return;
        }

        if ($respaldo->operacion === 'restauracion') {
            $servicio->restaurar($respaldo->id);
        } else {
            $servicio->crear($respaldo->id);
        }
    }

    // Si el trabajo muere sin controlar el error, el registro no se queda "en proceso" para siempre
    public function failed(\Throwable $e): void
    {
        $respaldo = Respaldo::find($this->respaldoId);

        if ($respaldo && $respaldo->enCurso()) {
            app(RespaldoService::class)->marcarError($respaldo, 'El proceso se interrumpió: ' . $e->getMessage());

            // La copia preventiva de una restauracion tampoco debe quedar "en espera" para siempre
            $preventivo = $respaldo->preventivo;

            if ($preventivo && $preventivo->enCurso()) {
                app(RespaldoService::class)->marcarError($preventivo, 'La restauración se interrumpió antes de terminar');
            }
        }
    }
}
