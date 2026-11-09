<?php

namespace App\Services;

use App\Mail\NotificacionCorreo;
use App\Models\Notificacion;
use App\Models\PaseLista;
use App\Models\Sesion;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NotificacionService
{
    // ---------------------------------------------------------------- recordatorios (revision periodica)

    // Crea el recordatorio de los momentos que empiezan en los proximos minutos y aun no tienen imagen
    public function generarRecordatorios(): int
    {
        $anticipacion = config('notificaciones.anticipacion_minutos');
        $zona = config('app.timezone_local', 'America/Mexico_City');

        $pases = PaseLista::with('sesion')
            ->where('estado', 'sin_evidencia')
            ->where('programado_en', '>', now())
            ->where('programado_en', '<=', now()->addMinutes($anticipacion))
            ->whereHas('sesion', fn ($q) => $q->where('estado', '!=', 'cancelada'))
            ->get();

        $creadas = 0;

        foreach ($pases as $pase) {
            $sesion = $pase->sesion;
            $momento = PaseLista::MOMENTOS[$pase->momento] ?? $pase->momento;
            $hora = $pase->programado_en->copy()->timezone($zona)->format('H:i');
            $contexto = $sesion->contexto_historico ?? [];

            foreach ($this->encargadosDe((int) $sesion->espacio_id) as $encargado) {
                $creadas += $this->crear(
                    $encargado,
                    'recordatorio',
                    'recordatorio:pase:' . $pase->id . ':v' . $pase->version_programacion,
                    'Recordatorio: pase de lista de ' . Str::lower($momento),
                    ($contexto['asignatura'] ?? 'Clase') . ' · grupo ' . data_get($contexto, 'grupo.clave', '—') . ' · ' . data_get($contexto, 'espacio.nombre', '—')
                        . '. A las ' . $hora . ' (hora de México) toca cargar la imagen del momento de ' . Str::lower($momento) . '.',
                    $this->datos($sesion, $pase, $encargado, ['programado_en' => $pase->programado_en->copy()->utc()->toIso8601String()]),
                    $pase->programado_en->copy()->subMinutes($anticipacion),
                    $pase->id
                );
            }
        }

        return $creadas;
    }

    // ---------------------------------------------------------------- eventos (los llaman los observers)

    // El profesor cambio la hora de un momento: los recordatorios anteriores dejan de valer y se avisa al encargado
    public function programacionCambiada(PaseLista $pase): void
    {
        $this->seguro(function () use ($pase) {
            $this->invalidarRecordatorios([$pase->id]);

            $sesion = $pase->sesion;

            if (!$sesion || $sesion->estado === 'cancelada') {
                return;
            }

            $zona = config('app.timezone_local', 'America/Mexico_City');
            $momento = PaseLista::MOMENTOS[$pase->momento] ?? $pase->momento;
            $contexto = $sesion->contexto_historico ?? [];
            $nueva = $pase->programado_en->copy()->timezone($zona)->format('d/m/Y H:i');

            foreach ($this->encargadosDe((int) $sesion->espacio_id) as $encargado) {
                $this->crear(
                    $encargado,
                    'cambio',
                    'cambio:pase:' . $pase->id . ':v' . $pase->version_programacion,
                    'Cambio de horario: ' . Str::lower($momento),
                    'El profesor cambió el momento de ' . Str::lower($momento) . ' de ' . ($contexto['asignatura'] ?? 'la clase') . '. Nueva hora: ' . $nueva . ' (hora de México).',
                    $this->datos($sesion, $pase, $encargado),
                    null,
                    $pase->id
                );
            }
        });
    }

    // La sesion se cancelo: se invalidan sus recordatorios y se avisa al encargado
    public function sesionCancelada(Sesion $sesion): void
    {
        $this->seguro(function () use ($sesion) {
            $this->invalidarRecordatorios($sesion->pases()->pluck('id')->all());

            $contexto = $sesion->contexto_historico ?? [];

            foreach ($this->encargadosDe((int) $sesion->espacio_id) as $encargado) {
                $this->crear(
                    $encargado,
                    'cambio',
                    'cambio:sesion:' . $sesion->id . ':cancelada',
                    'Sesión cancelada',
                    'Se canceló la sesión del ' . $sesion->fecha->format('d/m/Y') . ' de ' . ($contexto['asignatura'] ?? 'la clase') . '. No hace falta cargar imágenes.',
                    $this->datos($sesion, null, $encargado)
                );
            }
        });
    }

    // El analisis termino y hay un conteo listo para que el profesor lo valide
    public function resultadoListo(PaseLista $pase): void
    {
        $this->seguro(function () use ($pase) {
            $sesion = $pase->sesion;
            $proc = optional($pase->evidenciaVigente)->procesamientoActual;

            if (!$sesion || !$proc || $proc->estado !== 'procesado') {
                return;
            }

            $profesor = $sesion->profesor;

            if (!$profesor || !$profesor->activo) {
                return;
            }

            $momento = PaseLista::MOMENTOS[$pase->momento] ?? $pase->momento;
            $contexto = $sesion->contexto_historico ?? [];

            $this->crear(
                $profesor,
                'resultado',
                'resultado:proc:' . $proc->id,
                'Conteo listo para validar: ' . Str::lower($momento),
                'El sistema contó ' . $proc->conteo . ' persona(s) en el momento de ' . Str::lower($momento) . ' de ' . ($contexto['asignatura'] ?? 'la clase') . ' (' . $sesion->fecha->format('d/m/Y') . '). Revísalo y valídalo.',
                $this->datos($sesion, $pase, $profesor),
                null,
                $pase->id
            );
        });
    }

    // El momento quedo con incidencia: fallo el analisis o el encargado marco que la imagen no es visible
    public function incidenciaEnPase(PaseLista $pase): void
    {
        $this->seguro(function () use ($pase) {
            $sesion = $pase->sesion;

            if (!$sesion) {
                return;
            }

            $proc = optional($pase->evidenciaVigente)->procesamientoActual;
            $revision = $proc ? $proc->revisionActual : null;

            // Si el propio profesor observo el resultado, no se le avisa de su propia accion
            if ($revision && $revision->decision === 'observado') {
                return;
            }

            $momento = Str::lower(PaseLista::MOMENTOS[$pase->momento] ?? $pase->momento);
            $contexto = $sesion->contexto_historico ?? [];
            $asignatura = $contexto['asignatura'] ?? 'la clase';
            $destinatarios = collect();

            if ($sesion->profesor && $sesion->profesor->activo) {
                $destinatarios->push($sesion->profesor);
            }

            if ($proc && $proc->estado === 'error') {
                $clave = 'incidencia:proc:' . $proc->id;
                $titulo = 'Falló el análisis: ' . $momento;
                $mensaje = 'El análisis del momento de ' . $momento . ' de ' . $asignatura . ' no terminó: ' . Str::limit((string) $proc->error, 200) . '. Se puede reintentar desde los pases de lista.';

                // El encargado que lo solicito es quien puede reintentarlo
                $solicitante = $proc->solicitado_por ? Usuario::find($proc->solicitado_por) : null;

                if ($solicitante && $solicitante->activo && $solicitante->rol === 'encargado') {
                    $destinatarios->push($solicitante);
                }
            } else {
                $clave = 'incidencia:visibilidad:pase:' . $pase->id . ':' . now()->timestamp;
                $titulo = 'Imagen no visible: ' . $momento;
                $mensaje = 'Se marcó visibilidad insuficiente en el momento de ' . $momento . ' de ' . $asignatura . ' (' . $sesion->fecha->format('d/m/Y') . ').';
            }

            foreach ($destinatarios->unique('id') as $usuario) {
                $this->crear($usuario, 'incidencia', $clave, $titulo, $mensaje, $this->datos($sesion, $pase, $usuario), null, $pase->id);
            }
        });
    }

    // ---------------------------------------------------------------- envio por correo

    // Envia por correo las notificaciones pendientes; devuelve el conteo por resultado
    public function enviarPendientes(): array
    {
        $max = config('notificaciones.max_intentos');
        $resultado = ['enviado' => 0, 'error' => 0, 'caducado' => 0, 'omitida' => 0];

        $ids = Notificacion::whereIn('estado_correo', ['pendiente', 'error'])
            ->where('intentos_correo', '<', $max)
            ->where('programada_en', '<=', now())
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');

        foreach ($ids as $id) {
            $resultado[$this->enviar($id)]++;
        }

        return $resultado;
    }

    private function enviar(int $id): string
    {
        $max = config('notificaciones.max_intentos');
        $n = Notificacion::with('usuario')->find($id);

        if (!$n) {
            return 'omitida';
        }

        // Un aviso que ya no sirve no se envia: invalidado, usuario dado de baja o recordatorio de un momento que ya paso
        $vencido = !$n->vigente
            || !$n->usuario || !$n->usuario->activo
            || ($n->tipo === 'recordatorio' && Carbon::parse($n->datos['programado_en'] ?? now())->lte(now()));

        if ($vencido) {
            Notificacion::whereKey($id)->whereIn('estado_correo', ['pendiente', 'error'])->update(['estado_correo' => 'caducado']);

            return 'caducado';
        }

        // Se toma el envio de forma atomica: si el proceso muriera a medias, queda "incierto" y no se reenvia solo
        $tomada = Notificacion::whereKey($id)
            ->whereIn('estado_correo', ['pendiente', 'error'])
            ->where('intentos_correo', '<', $max)
            ->update(['estado_correo' => 'incierto', 'intentos_correo' => DB::raw('intentos_correo + 1')]);

        if (!$tomada) {
            return 'omitida';
        }

        try {
            Mail::to($n->usuario->correo)->send(new NotificacionCorreo($n));

            Notificacion::whereKey($id)->update([
                'estado_correo' => 'enviado',
                'correo_enviado_en' => now(),
                'ultimo_error' => null,
            ]);

            return 'enviado';
        } catch (\Throwable $e) {
            Notificacion::whereKey($id)->update([
                'estado_correo' => 'error',
                'ultimo_error' => $this->sinSecretos($e->getMessage()),
            ]);

            return 'error';
        }
    }

    // ---------------------------------------------------------------- auxiliares

    // Inserta ignorando duplicados: devuelve 1 si la notificacion era nueva y 0 si ya existia
    private function crear(Usuario $usuario, string $tipo, string $clave, string $titulo, string $mensaje, array $datos, ?Carbon $programada = null, ?int $paseId = null): int
    {
        $ahora = now()->format('Y-m-d H:i:s');

        return DB::table('notificaciones')->insertOrIgnore([[
            'usuario_id' => $usuario->id,
            'pase_lista_id' => $paseId,
            'tipo' => $tipo,
            'clave_evento' => Str::limit($clave, 160, ''),
            'titulo' => Str::limit($titulo, 150, ''),
            'mensaje' => $mensaje,
            'datos' => json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'programada_en' => ($programada ?? now())->copy()->utc()->format('Y-m-d H:i:s'),
            'vigente' => 1,
            'estado_correo' => 'pendiente',
            'intentos_correo' => 0,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]]);
    }

    private function invalidarRecordatorios(array $paseIds): void
    {
        if (!$paseIds) {
            return;
        }

        Notificacion::whereIn('pase_lista_id', $paseIds)->where('tipo', 'recordatorio')->where('vigente', 1)->update(['vigente' => 0]);

        Notificacion::whereIn('pase_lista_id', $paseIds)->where('tipo', 'recordatorio')
            ->whereIn('estado_correo', ['pendiente', 'error'])->update(['estado_correo' => 'caducado']);
    }

    private function encargadosDe(int $espacioId)
    {
        return Usuario::where('rol', 'encargado')->where('activo', 1)
            ->whereIn('id', DB::table('encargado_espacio')->where('espacio_id', $espacioId)->where('activo', 1)->select('usuario_id'))
            ->get();
    }

    // Datos de la notificacion, con la ruta a la que lleva segun el rol de quien la recibe (ruta relativa, sin dominio)
    private function datos(Sesion $sesion, ?PaseLista $pase, Usuario $destinatario, array $extra = []): array
    {
        $ruta = match ($destinatario->rol) {
            'profesor' => $pase && $pase->estado === 'procesado'
                ? route('sesiones.revision', [$sesion->id, $pase->id], false)
                : route('sesiones.show', $sesion->id, false),
            'encargado' => route('pases.show', $sesion->id, false),
            default => '/notificaciones',
        };

        return $extra + [
            'sesion_id' => $sesion->id,
            'pase_id' => $pase?->id,
            'ruta' => $ruta,
        ];
    }

    // Un fallo al notificar nunca debe romper la accion principal (subir, validar, programar)
    private function seguro(callable $accion): void
    {
        try {
            $accion();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // Los mensajes de error se guardan sin credenciales
    private function sinSecretos(string $mensaje): string
    {
        $password = (string) config('mail.mailers.smtp.password');

        if ($password !== '') {
            $mensaje = str_replace($password, '***', $mensaje);
        }

        return Str::limit($mensaje, 500);
    }
}
