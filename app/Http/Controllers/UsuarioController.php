<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\ActivacionService;
use App\Services\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsuarioController extends Controller
{
    private const MENSAJE_ULTIMO_ADMIN = 'No se puede desactivar ni cambiar de rol al último administrador activo';

    public function index(Request $request)
    {
        $consulta = Usuario::query();

        // Busqueda por nombre o correo
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;

            $consulta->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('correo', 'like', "%{$buscar}%");
            });
        }

        // Filtros por rol y estado
        if ($request->filled('rol')) {
            $consulta->where('rol', $request->rol);
        }

        if ($request->filled('estado')) {
            $consulta->where('activo', $request->estado === 'activo' ? 1 : 0);
        }

        $usuarios = $consulta->orderBy('nombre')->paginate(10)->withQueryString();

        // Solo se puede eliminar definitivamente a quien no tiene ningun registro relacionado
        // (nunca la propia cuenta)
        $ids = $usuarios->pluck('id')->map(fn ($id) => (int) $id);
        $eliminables = $ids->diff(Usuario::idsConRelaciones($ids))
            ->reject(fn ($id) => $id === (int) Auth::id())
            ->values();

        return view('usuarios.index', compact('usuarios', 'eliminables'));
    }

    public function create()
    {
        return view('usuarios.create');
    }

    public function store(Request $request, ActivacionService $activacion)
    {
        // El correo se guarda normalizado (minusculas)
        $request->merge(['correo' => Str::lower((string) $request->correo)]);

        $request->validate([
            'nombre' => 'required|string|max:150',
            'correo' => 'required|email|max:254|unique:usuarios,correo',
            'rol' => 'required|in:administrador,encargado,profesor',
            'activo' => 'required|in:0,1',
        ]);

        // El administrador no conoce ninguna contraseña: se guarda una aleatoria que nadie recibe
        // y la cuenta queda pendiente (activado_en vacio) hasta que su dueño elija la suya
        $usuario = Usuario::create([
            'nombre' => $request->nombre,
            'correo' => $request->correo,
            'password' => Hash::make(Str::random(64)),
            'rol' => $request->rol,
            'activo' => $request->activo,
        ]);

        if (!$usuario->activo) {
            return redirect()->route('usuarios.index')
                ->with('success', 'Usuario creado como inactivo. Al reactivarlo se le enviará el enlace de activación');
        }

        if ($activacion->enviar($usuario) === ActivacionService::ENVIADO) {
            return redirect()->route('usuarios.index')
                ->with('success', 'Usuario creado. Se envió un enlace de activación a ' . $usuario->correo);
        }

        return redirect()->route('usuarios.index')
            ->with('error', 'Usuario creado, pero no se pudo enviar el correo de activación. Usa «Reenviar activación» cuando se corrija el envío');
    }

    public function edit(Usuario $usuario)
    {
        return view('usuarios.edit', compact('usuario'));
    }

    public function update(Request $request, Usuario $usuario, ActivacionService $activacion)
    {
        $request->merge(['correo' => Str::lower((string) $request->correo)]);

        $request->validate([
            'nombre' => 'required|string|max:150',
            'correo' => 'required|email|max:254|unique:usuarios,correo,' . $usuario->id,
            'rol' => 'required|in:administrador,encargado,profesor',
            'activo' => 'required|in:0,1',
        ]);

        $correoCambio = $usuario->correo !== $request->correo;

        $error = DB::transaction(function () use ($request, $usuario, $activacion) {

            if ($this->dejaSinAdministrador($usuario, $request->rol, (bool) $request->activo)) {
                return self::MENSAJE_ULTIMO_ADMIN;
            }

            $datos = [
                'nombre' => $request->nombre,
                'correo' => $request->correo,
                'rol' => $request->rol,
                'activo' => $request->activo,
            ];

            // Cambios que afectan el acceso: correo, rol, estado o contraseña
            $cambioAcceso = $usuario->correo !== $request->correo
                || $usuario->rol !== $request->rol
                || (int) $usuario->activo !== (int) $request->activo;

            // Un correo nuevo elimina los enlaces de recuperacion y de activacion pendientes del anterior
            if ($usuario->correo !== $request->correo) {
                DB::table('password_reset_tokens')->where('email', $usuario->correo)->delete();
                $activacion->descartar($usuario->correo);
            }

            // Subir la version invalida las sesiones abiertas de ese usuario
            if ($cambioAcceso) {
                $datos['version_seguridad'] = $usuario->version_seguridad + 1;
            }

            $usuario->update($datos);

            // Si el administrador se edita a si mismo, conserva su sesion actual
            if ($cambioAcceso && $usuario->id === Auth::id()) {
                session()->put('version_seguridad', $usuario->version_seguridad);
            }

            return null;
        });

        if ($error) {
            return back()->withInput()->with('error', $error);
        }

        $usuario->refresh();

        // Si una cuenta pendiente cambio de correo, el enlace nuevo va al correo nuevo
        if ($correoCambio && $activacion->pendiente($usuario) && $usuario->activo) {
            $envio = $activacion->enviar($usuario);

            return redirect()->route('usuarios.index')->with(
                $envio === ActivacionService::ENVIADO ? 'success' : 'error',
                $envio === ActivacionService::ENVIADO
                    ? 'Usuario actualizado. Se envió un nuevo enlace de activación a ' . $usuario->correo
                    : 'Usuario actualizado, pero no se pudo enviar el enlace de activación. Usa «Reenviar activación»'
            );
        }

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario actualizado correctamente');
    }

    // Baja logica: desactiva o reactiva la cuenta (nunca se elimina el registro)
    public function cambiarEstado(Usuario $usuario, ActivacionService $activacion)
    {
        $nuevoActivo = !$usuario->activo;

        $error = DB::transaction(function () use ($usuario, $nuevoActivo) {

            if ($this->dejaSinAdministrador($usuario, $usuario->rol, $nuevoActivo)) {
                return self::MENSAJE_ULTIMO_ADMIN;
            }

            $usuario->update([
                'activo' => $nuevoActivo,
                'version_seguridad' => $usuario->version_seguridad + 1,
            ]);

            return null;
        });

        if ($error) {
            return back()->with('error', $error);
        }

        if ($nuevoActivo) {
            $usuario->refresh();

            if ($activacion->pendiente($usuario)) {
                $envio = $activacion->enviar($usuario);

                return back()->with(
                    $envio === ActivacionService::ENVIADO ? 'success' : 'error',
                    $envio === ActivacionService::ENVIADO
                        ? 'Usuario reactivado. Se envió el enlace de activación a ' . $usuario->correo
                        : 'Usuario reactivado, pero no se pudo enviar el enlace de activación. Usa «Reenviar activación»'
                );
            }
        }

        return back()->with(
            'success',
            $nuevoActivo ? 'Usuario reactivado correctamente' : 'Usuario desactivado correctamente'
        );
    }

    // Genera un enlace de activacion nuevo (el anterior deja de servir)
    public function reenviarActivacion(Usuario $usuario, ActivacionService $activacion)
    {
        if (!$activacion->pendiente($usuario)) {
            return back()->with('error', 'Esta cuenta ya fue activada');
        }

        if (!$usuario->activo) {
            return back()->with('error', 'Reactiva la cuenta antes de enviar el enlace de activación');
        }

        $envio = $activacion->enviar($usuario);

        if ($envio === ActivacionService::ESPERA) {
            return back()->with('error', 'Ya se envió un enlace hace menos de un minuto. Espera un poco antes de reenviarlo');
        }

        if ($envio === ActivacionService::ERROR) {
            return back()->with('error', 'No se pudo enviar el correo. Revisa la configuración de correo e inténtalo de nuevo');
        }

        return back()->with('success', 'Enlace de activación enviado a ' . $usuario->correo);
    }

    // Eliminacion definitiva: solo si el usuario no tiene registros relacionados (clases, sesiones,
    // asignaciones, evidencias, historial...). Si ya tiene historial, solo se desactiva
    public function eliminar(Usuario $usuario, AuditoriaService $auditoria)
    {
        if ((int) $usuario->id === (int) Auth::id()) {
            return back()->with('error', 'No puedes eliminar tu propia cuenta');
        }

        try {
            $error = DB::transaction(function () use ($usuario, $auditoria) {
                if ($this->dejaSinAdministrador($usuario, $usuario->rol, false)) {
                    return 'No se puede eliminar al último administrador activo';
                }

                if (Usuario::idsConRelaciones([$usuario->id])->isNotEmpty()) {
                    return 'No se puede eliminar definitivamente: el usuario tiene registros relacionados (clases, sesiones, asignaciones o historial). Desactívalo para conservar su historial';
                }

                // Nunca se guarda la contrasena en la bitacora
                $auditoria->registrar('usuarios', $usuario->id, 'eliminacion_definitiva', [
                    'nombre' => $usuario->nombre,
                    'correo' => $usuario->correo,
                    'rol' => $usuario->rol,
                    'activo' => (bool) $usuario->activo,
                ]);

                // Sesiones abiertas de esta cuenta; los tokens de activacion y recuperacion se borran en cascada
                DB::table('sessions')->where('user_id', $usuario->id)->delete();

                $usuario->delete();

                return null;
            });
        } catch (QueryException $e) {
            $error = 'No se pudo eliminar el usuario porque tiene registros relacionados. Desactívalo en su lugar';
        }

        if ($error) {
            return back()->with('error', $error);
        }

        return redirect()->route('usuarios.index')
            ->with('success', 'Usuario eliminado definitivamente');
    }

    // Indica si el cambio dejaria al sistema sin administradores activos
    private function dejaSinAdministrador(Usuario $usuario, string $nuevoRol, bool $nuevoActivo): bool
    {
        // Solo importa si hoy es administrador activo y dejaria de serlo
        if ($usuario->rol !== 'administrador' || !$usuario->activo) {
            return false;
        }

        if ($nuevoRol === 'administrador' && $nuevoActivo) {
            return false;
        }

        // Se bloquean las filas para que dos cambios simultaneos no vacien el grupo
        $activos = Usuario::where('rol', 'administrador')
            ->where('activo', 1)
            ->lockForUpdate()
            ->pluck('id');

        return $activos->count() <= 1;
    }
}
