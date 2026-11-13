<?php

namespace App\Jobs;

use App\Services\InferenciaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Queue\Queueable;
/**
 * Trabajo en cola que ejecuta el análisis de una imagen (FN.9).
 * Se publica en la cola «inferencia» y lo atiende el trabajador de Laravel.
 */
// Define un trabajo en segundo plano para procesar el conteo.
class ProcesarConteo implements ShouldQueue
{
    // Incorpora las funcionalidades necesarias para gestionar la cola.
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Limita la ejecución a un solo intento, sin reintentos automáticos.
    public int $tries = 1;

    // Establece el tiempo máximo de ejecución en 1000 segundos (YOLO + SAM 3).
    public int $timeout = 1000;

    // Recibe y almacena el identificador del procesamiento.
    public function __construct(public int $procesamientoId)
    {
    }

    // Ejecuta el servicio encargado de realizar la inferencia.
    public function handle(InferenciaService $servicio): void
    {
        $servicio->ejecutar($this->procesamientoId);
    }

    // Registra el fallo si el trabajo termina con una excepción.
    public function failed(\Throwable $e): void
    {
        app(InferenciaService::class)->falloInterno($this->procesamientoId, $e);
    }
}
