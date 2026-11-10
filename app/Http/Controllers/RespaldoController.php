<?php

namespace App\Http\Controllers;

use App\Jobs\EjecutarRespaldo;
use App\Models\Respaldo;
use App\Services\AuditoriaService;
use App\Services\RespaldoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RespaldoController extends Controller
{
    public function index()
    {
        $respaldos = Respaldo::with(['administrador', 'origen', 'preventivo'])
            ->orderByDesc('id')
            ->paginate(15);

        $hayEnCurso = Respaldo::whereIn('estado', ['pendiente', 'en_proceso'])->exists();

        return view('respaldos.index', compact('respaldos', 'hayEnCurso'));
    }

    // Solicita una copia nueva; la genera el worker de colas
    public function store(AuditoriaService $auditoria)
    {
        $respaldo = $this->crearRegistro('respaldo');

        if (!$respaldo) {
            return back()->with('error', 'Ya hay una copia o restauración en curso. Espera a que termine');
        }

        $auditoria->registrar('respaldo', $respaldo->id, 'solicitar_respaldo');
        EjecutarRespaldo::dispatch($respaldo->id);

        return redirect()->route('respaldos.index')
            ->with('success', 'Copia solicitada. Se genera en segundo plano, recarga la página en unos momentos');
    }

    // Pantalla de confirmacion antes de restaurar
    public function confirmar(Respaldo $respaldo, RespaldoService $servicio)
    {
        if ($respaldo->operacion !== 'respaldo' || $respaldo->estado !== 'completado') {
            return redirect()->route('respaldos.index')->with('error', 'Solo se puede restaurar una copia completada');
        }

        // La verificacion se muestra antes de decidir
        $problemas = $servicio->verificar($respaldo);

        return view('respaldos.confirmar', compact('respaldo', 'problemas'));
    }

    public function restaurar(Request $request, Respaldo $respaldo, RespaldoService $servicio, AuditoriaService $auditoria)
    {
        $request->validate([
            'confirmacion' => 'required|in:RESTAURAR',
            'password' => 'required',
        ], [
            'confirmacion.in' => 'Escribe la palabra RESTAURAR exactamente como se indica',
            'password.required' => 'Escribe tu contraseña para confirmar',
        ]);

        if (!Hash::check($request->password, Auth::user()->password)) {
            return back()->withErrors(['password' => 'La contraseña no es correcta']);
        }

        if ($respaldo->operacion !== 'respaldo' || $respaldo->estado !== 'completado') {
            return redirect()->route('respaldos.index')->with('error', 'Solo se puede restaurar una copia completada');
        }

        $problemas = $servicio->verificar($respaldo);

        if ($problemas) {
            return redirect()->route('respaldos.confirmar', $respaldo->id)
                ->with('error', 'La copia no pasó la verificación de integridad, no se puede restaurar');
        }

        $restauracion = $this->crearRegistro('restauracion', $respaldo->id);

        if (!$restauracion) {
            return back()->with('error', 'Ya hay una copia o restauración en curso. Espera a que termine');
        }

        $auditoria->registrar('respaldo', $restauracion->id, 'solicitar_restauracion', null, ['copia_origen' => $respaldo->id]);
        EjecutarRespaldo::dispatch($restauracion->id);

        return redirect()->route('respaldos.index')
            ->with('success', 'Restauración solicitada. Se genera primero una copia preventiva y después se restaura. Al terminar tendrás que iniciar sesión de nuevo');
    }

    public function descargar(Respaldo $respaldo, RespaldoService $servicio)
    {
        $ruta = $servicio->rutaSegura($respaldo->ruta);

        if ($respaldo->operacion !== 'respaldo' || $respaldo->estado !== 'completado' || !$ruta || !is_file($ruta)) {
            return redirect()->route('respaldos.index')->with('error', 'El archivo de la copia no está disponible');
        }

        app(AuditoriaService::class)->registrar('respaldo', $respaldo->id, 'descargar');

        return Storage::disk('local')->download($respaldo->ruta, 'respaldo_' . $respaldo->id . '.zip');
    }

    // Crea el registro de la operacion; solo una a la vez. Una restauracion crea tambien su copia preventiva.
    private function crearRegistro(string $operacion, ?int $origenId = null): ?Respaldo
    {
        return DB::transaction(function () use ($operacion, $origenId) {

            // Se bloquea el historial para que dos solicitudes simultaneas no arranquen a la vez
            $enCurso = Respaldo::whereIn('estado', ['pendiente', 'en_proceso'])->lockForUpdate()->exists();

            if ($enCurso) {
                return null;
            }

            $datos = [
                'administrador_id' => Auth::id(),
                'estado' => 'pendiente',
                'iniciado_en' => now(),
            ];

            if ($operacion === 'restauracion') {
                $preventivo = Respaldo::create($datos + [
                    'operacion' => 'respaldo',
                    'detalle' => 'Copia preventiva antes de restaurar la copia #' . $origenId,
                ]);

                // El preventivo lo ejecuta la propia restauracion: queda en espera, sin job propio
                return Respaldo::create($datos + [
                    'operacion' => 'restauracion',
                    'respaldo_origen_id' => $origenId,
                    'respaldo_preventivo_id' => $preventivo->id,
                ]);
            }

            return Respaldo::create($datos + ['operacion' => 'respaldo']);
        });
    }
}
