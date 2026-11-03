<?php

namespace App\Http\Controllers;

use App\Models\PaseLista;
use App\Models\RevisionConteo;
use App\Models\Sesion;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RevisionController extends Controller
{
    private const MENSAJE_SIN_PERMISO = 'No tienes permiso para revisar los pases de esa sesión';

    // Solo el profesor titular revisa; el encargado no puede validar
    public function show(Sesion $sesion, PaseLista $pase)
    {
        if (!$this->permitido($sesion, $pase)) {
            return redirect()->route('sesiones.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        $pase->load(['evidenciaVigente.procesamientoActual.revisiones.profesor']);

        $evidencia = $pase->evidenciaVigente;
        $proc = $evidencia ? $evidencia->procesamientoActual : null;

        if (!$proc || $proc->estado !== 'procesado') {
            return redirect()->route('sesiones.show', $sesion->id)
                ->with('error', 'Este momento todavía no tiene un resultado procesado para revisar');
        }

        return view('sesiones.revision', compact('sesion', 'pase', 'evidencia', 'proc'));
    }

    public function guardar(Request $request, Sesion $sesion, PaseLista $pase, AuditoriaService $auditoria)
    {
        if (!$this->permitido($sesion, $pase)) {
            return redirect()->route('sesiones.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        if ($sesion->estado === 'cancelada') {
            return back()->with('error', 'La sesión está cancelada y no admite revisiones');
        }

        $request->validate([
            'procesamiento_id' => 'required|integer',
            'decision' => 'required|in:validado,observado',
            'conteo_revisado' => 'nullable|integer|min:0',
            'motivo' => 'nullable|string|max:1000',
        ], [
            'decision.required' => 'Elige si validas u observas el resultado',
            'decision.in' => 'La decisión no es válida',
            'conteo_revisado.integer' => 'El conteo debe ser un número entero',
            'conteo_revisado.min' => 'El conteo no puede ser negativo',
        ], [
            'conteo_revisado' => 'conteo revisado',
        ]);

        $decision = $request->decision;

        $error = DB::transaction(function () use ($request, $sesion, $pase, $auditoria, $decision) {

            // Se bloquea el pase: la revision se aplica sobre el resultado que el profesor realmente vio
            $bloqueado = PaseLista::whereKey($pase->id)->lockForUpdate()->first();
            $evidencia = $bloqueado->evidenciaVigente;
            $proc = $evidencia ? $evidencia->procesamientoActual : null;

            if (!$proc || $proc->estado !== 'procesado' || $proc->id !== (int) $request->procesamiento_id) {
                return 'El resultado cambió mientras lo revisabas. Recarga la página y vuelve a revisarlo';
            }

            $motivo = $request->filled('motivo') ? trim($request->motivo) : null;
            $revisado = null;

            if ($decision === 'validado') {
                if (!$request->filled('conteo_revisado')) {
                    throw ValidationException::withMessages(['conteo_revisado' => 'Indica el conteo que validas']);
                }

                $revisado = (int) $request->conteo_revisado;

                // Un conteo mayor que los inscritos nunca se recorta ni se valida: se corrige u observa
                if ($revisado > (int) $sesion->inscritos) {
                    throw ValidationException::withMessages([
                        'conteo_revisado' => "El conteo no puede ser mayor que los inscritos de la sesión ({$sesion->inscritos}). Corrígelo u observa el resultado",
                    ]);
                }

                // Toda correccion del conteo automatico exige un motivo
                if ($revisado !== (int) $proc->conteo && !$motivo) {
                    throw ValidationException::withMessages(['motivo' => 'Explica por qué corriges el conteo automático']);
                }
            } elseif (!$motivo) {
                throw ValidationException::withMessages(['motivo' => 'Explica por qué observas el resultado']);
            }

            // El historial solo crece: nunca se edita ni se borra una revision anterior
            RevisionConteo::create([
                'procesamiento_id' => $proc->id,
                'profesor_id' => Auth::id(),
                'decision' => $decision,
                'conteo_revisado' => $revisado,
                'motivo' => $motivo,
            ]);

            $estadoAntes = $bloqueado->estado;
            $datos = ['estado' => $decision === 'validado' ? 'validado' : 'con_incidencia'];

            if ($decision === 'observado') {
                $zona = config('app.timezone_local', 'America/Mexico_City');
                $nota = '[Resultado observado por el profesor · ' . now()->timezone($zona)->format('d/m/Y H:i') . '] ' . $motivo;
                $datos['observaciones'] = trim(($bloqueado->observaciones ? $bloqueado->observaciones . "\n" : '') . $nota);
            }

            $bloqueado->update($datos);

            $auditoria->registrar(
                'pases_lista',
                $bloqueado->id,
                'revision',
                ['estado' => $estadoAntes, 'conteo_automatico' => $proc->conteo],
                ['estado' => $datos['estado'], 'decision' => $decision, 'conteo_revisado' => $revisado],
                $motivo
            );

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return redirect()->route('sesiones.show', $sesion->id)->with(
            'success',
            $decision === 'validado' ? 'Conteo validado correctamente' : 'Resultado observado. El pase queda con incidencia'
        );
    }

    private function permitido(Sesion $sesion, PaseLista $pase): bool
    {
        return (int) $sesion->profesor_id === (int) Auth::id()
            && (int) $pase->sesion_id === (int) $sesion->id;
    }
}
