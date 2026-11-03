<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Sesion;
use App\Services\AuditoriaService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AsistenciaController extends Controller
{
    private const MENSAJE_SIN_PERMISO = 'No tienes permiso para gestionar esa sesión';
    private const MENSAJE_CONFLICTO = 'El registro cambió mientras lo editabas. Recarga la página y vuelve a intentarlo';

    // Guarda como borrador o confirma el resumen de asistencia de la sesion
    public function guardar(Request $request, Sesion $sesion)
    {
        if (!$this->esTitular($sesion)) {
            return redirect()->route('sesiones.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        if ($sesion->estado === 'cancelada') {
            return back()->with('error', 'La sesión está cancelada y no admite registro de asistencia');
        }

        $asistencia = $sesion->asistencia;

        if ($asistencia && $asistencia->estado === 'confirmado') {
            return back()->with('error', 'La asistencia ya está confirmada. Reábrela para revisión si necesitas corregirla');
        }

        $confirmando = $request->accion === 'confirmar';

        // En borrador las cantidades pueden quedar vacias; al confirmar son obligatorias
        $obligatorio = $confirmando ? 'required' : 'nullable';

        $request->validate([
            'accion' => 'required|in:borrador,confirmar',
            'asistentes' => [$obligatorio, 'integer', 'min:0'],
            'justificados' => [$obligatorio, 'integer', 'min:0'],
            'faltas' => [$obligatorio, 'integer', 'min:0'],
            'observaciones' => 'nullable|string|max:5000',
            'version' => 'nullable|integer',
        ], [
            'asistentes.integer' => 'Los asistentes deben ser un número entero',
            'justificados.integer' => 'Las ausencias justificadas deben ser un número entero',
            'faltas.integer' => 'Las faltas deben ser un número entero',
            'asistentes.min' => 'Los asistentes no pueden ser negativos',
            'justificados.min' => 'Las ausencias justificadas no pueden ser negativas',
            'faltas.min' => 'Las faltas no pueden ser negativas',
            'asistentes.required' => 'Captura los asistentes para confirmar',
            'justificados.required' => 'Captura las ausencias justificadas para confirmar',
            'faltas.required' => 'Captura las faltas para confirmar',
        ]);

        if ($confirmando) {
            $suma = (int) $request->asistentes + (int) $request->justificados + (int) $request->faltas;

            if ($suma !== (int) $sesion->inscritos) {
                throw ValidationException::withMessages([
                    'asistentes' => "La suma de asistentes, justificados y faltas ($suma) debe ser igual a los inscritos de la sesión ({$sesion->inscritos})",
                ]);
            }
        }

        $datos = [
            'asistentes' => $request->asistentes,
            'justificados' => $request->justificados,
            'faltas' => $request->faltas,
            'observaciones' => $request->observaciones,
        ];

        if ($confirmando) {
            $datos['estado'] = 'confirmado';
            $datos['confirmado_por'] = Auth::id();
            $datos['confirmado_en'] = now();
        }

        $conflicto = DB::transaction(function () use ($request, $sesion, $asistencia, $datos, $confirmando) {

            if (!$asistencia) {
                try {
                    Asistencia::create($datos + [
                        'sesion_id' => $sesion->id,
                        'estado' => $confirmando ? 'confirmado' : 'borrador',
                        'version' => 1,
                    ]);
                } catch (UniqueConstraintViolationException $e) {
                    return true;
                }
            } else {
                // Control de ediciones simultaneas: solo se guarda si la version no cambio
                $version = (int) $request->version;

                $filas = Asistencia::where('id', $asistencia->id)
                    ->where('version', $version)
                    ->update($datos + ['version' => $version + 1]);

                if ($filas === 0) {
                    return true;
                }
            }

            // Al confirmar, la sesion programada pasa a realizada
            if ($confirmando && $sesion->estado === 'programada') {
                $sesion->update(['estado' => 'realizada']);
            }

            return false;
        });

        if ($conflicto) {
            return back()->withInput()->with('error', self::MENSAJE_CONFLICTO);
        }

        return back()->with(
            'success',
            $confirmando ? 'Asistencia confirmada correctamente' : 'Borrador guardado correctamente'
        );
    }

    // confirmado -> pendiente_revision, para corregir cantidades y volver a confirmar
    public function reabrir(Request $request, Sesion $sesion, AuditoriaService $auditoria)
    {
        if (!$this->esTitular($sesion)) {
            return redirect()->route('sesiones.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        $request->validate([
            'motivo_reapertura' => 'required|string|max:500',
            'version' => 'required|integer',
        ], [
            'motivo_reapertura.required' => 'Escribe el motivo de la reapertura',
        ]);

        $asistencia = $sesion->asistencia;

        if (!$asistencia || $asistencia->estado !== 'confirmado') {
            return back()->with('error', 'Solo se puede reabrir una asistencia confirmada');
        }

        $zona = config('app.timezone_local', 'America/Mexico_City');
        $nota = '[Reapertura ' . now()->timezone($zona)->format('d/m/Y H:i') . '] ' . $request->motivo_reapertura;
        $version = (int) $request->version;

        $conflicto = DB::transaction(function () use ($asistencia, $version, $nota, $request, $auditoria) {
            $filas = Asistencia::where('id', $asistencia->id)
                ->where('version', $version)
                ->update([
                    'estado' => 'pendiente_revision',
                    'observaciones' => trim(($asistencia->observaciones ? $asistencia->observaciones . "\n" : '') . $nota),
                    'version' => $version + 1,
                ]);

            if ($filas === 0) {
                return true;
            }

            // Valores anteriores, estado nuevo, motivo, responsable (usuario) y fecha quedan en la bitacora
            $auditoria->registrar(
                'asistencias',
                $asistencia->id,
                'reapertura',
                [
                    'estado' => 'confirmado',
                    'asistentes' => $asistencia->asistentes,
                    'justificados' => $asistencia->justificados,
                    'faltas' => $asistencia->faltas,
                ],
                ['estado' => 'pendiente_revision'],
                $request->motivo_reapertura
            );

            return false;
        });

        if ($conflicto) {
            return back()->with('error', self::MENSAJE_CONFLICTO);
        }

        return back()->with('success', 'Asistencia reabierta. Corrige las cantidades y vuelve a confirmar');
    }

    private function esTitular(Sesion $sesion): bool
    {
        return (int) $sesion->profesor_id === (int) Auth::id();
    }
}
