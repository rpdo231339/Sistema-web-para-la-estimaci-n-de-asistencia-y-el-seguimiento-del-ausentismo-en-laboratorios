<?php

namespace App\Http\Controllers;

use App\Models\Clase;
use App\Models\Espacio;
use App\Models\Grupo;
use App\Models\HorarioClase;
use App\Models\Sesion;
use App\Services\AuditoriaService;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EspacioController extends Controller
{
    private const MENSAJE_SIN_PERMISO = 'No tienes permiso para gestionar ese espacio';

    public function index(Request $request)
    {
        $usuario = Auth::user();

        // bloques_vigentes: bloques de clases vigentes que usan el espacio (para advertir al desactivar)
        $consulta = Espacio::query()
            ->with('encargadosActivos')
            ->withCount(['horarios as bloques_vigentes' => function ($q) {
                $q->where('activo', 1)->whereHas('clase', function ($c) {
                    $c->where('vigente', 1);
                });
            }]);

        // El encargado solo ve los espacios que tiene asignados
        if ($usuario->rol === 'encargado') {
            $consulta->whereIn('id', $usuario->espaciosAsignados()->pluck('espacios.id'));
        }

        // Busqueda por nombre o ubicacion
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;

            $consulta->where(function ($q) use ($buscar) {
                $q->where('nombre', 'like', "%{$buscar}%")
                  ->orWhere('ubicacion', 'like', "%{$buscar}%");
            });
        }

        // Filtros por tipo y estado
        if ($request->filled('tipo')) {
            $consulta->where('tipo', $request->tipo);
        }

        if ($request->filled('estado')) {
            $consulta->where('activo', $request->estado === 'activo' ? 1 : 0);
        }

        $espacios = $consulta->orderBy('nombre')->paginate(10)->withQueryString();

        // Solo el administrador elimina; y solo espacios que ningun bloque ni sesion ha usado
        $ids = $espacios->pluck('id');
        $usados = HorarioClase::whereIn('espacio_id', $ids)->pluck('espacio_id')
            ->merge(Sesion::whereIn('espacio_id', $ids)->pluck('espacio_id'))
            ->unique();
        $eliminables = $usuario->rol === 'administrador' ? $ids->diff($usados)->values() : collect();

        return view('espacios.index', compact('espacios', 'eliminables'));
    }

    public function create()
    {
        return view('espacios.create');
    }

    public function store(Request $request)
    {
        $request->validate($this->reglas($request), $this->mensajes());

        DB::transaction(function () use ($request) {
            $espacio = Espacio::create([
                'nombre' => $request->nombre,
                'tipo' => $request->tipo,
                'ubicacion' => $request->ubicacion,
                'descripcion' => $request->descripcion,
                'activo' => $request->activo,
            ]);

            // Si lo registra un encargado, el espacio queda asignado a el
            if (Auth::user()->rol === 'encargado') {
                $espacio->encargados()->attach(Auth::id(), ['activo' => 1]);
            }
        });

        return redirect()->route('espacios.index')
            ->with('success', 'Espacio registrado correctamente');
    }

    public function edit(Request $request, Espacio $espacio)
    {
        if (!$this->puedeGestionar($espacio)) {
            return redirect()->route('espacios.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        $asignados = $espacio->encargadosActivos()->orderBy('nombre')->get();

        // Encargados activos que todavia no estan asignados a este espacio
        $candidatos = collect();

        if (Auth::user()->rol === 'administrador') {
            $candidatos = Usuario::where('rol', 'encargado')
                ->where('activo', 1)
                ->whereNotIn('id', $asignados->pluck('id'))
                ->orderBy('nombre')
                ->get();
        }

        // Clases vigentes con al menos un bloque en este espacio (solo consulta)
        $baseClases = Clase::where('vigente', 1)->whereHas('horariosActivos', function ($q) use ($espacio) {
            $q->where('espacio_id', $espacio->id);
        });

        $periodosClases = Grupo::whereIn('id', (clone $baseClases)->select('grupo_id'))
            ->select('periodo')
            ->distinct()
            ->orderByDesc('periodo')
            ->pluck('periodo');

        $consultaClases = (clone $baseClases)->with([
            'grupo',
            'profesor',
            'horariosActivos' => function ($q) use ($espacio) {
                $q->where('espacio_id', $espacio->id);
            },
        ]);

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;

            $consultaClases->where(function ($q) use ($buscar) {
                $q->where('asignatura', 'like', "%{$buscar}%")
                  ->orWhereHas('profesor', function ($p) use ($buscar) {
                      $p->where('nombre', 'like', "%{$buscar}%");
                  });
            });
        }

        if ($request->filled('periodo')) {
            $periodo = $request->periodo;

            $consultaClases->whereHas('grupo', function ($g) use ($periodo) {
                $g->where('periodo', $periodo);
            });
        }

        $clasesVinculadas = $consultaClases->orderBy('asignatura')
            ->paginate(10, ['*'], 'clases_page')
            ->withQueryString();

        return view('espacios.edit', compact('espacio', 'asignados', 'candidatos', 'clasesVinculadas', 'periodosClases'));
    }

    public function update(Request $request, Espacio $espacio)
    {
        if (!$this->puedeGestionar($espacio)) {
            return redirect()->route('espacios.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        $request->validate($this->reglas($request, $espacio), $this->mensajes());

        $espacio->update([
            'nombre' => $request->nombre,
            'tipo' => $request->tipo,
            'ubicacion' => $request->ubicacion,
            'descripcion' => $request->descripcion,
            'activo' => $request->activo,
        ]);

        return redirect()->route('espacios.index')
            ->with('success', 'Espacio actualizado correctamente');
    }

    // Baja logica: desactiva o reactiva el espacio (nunca se elimina el registro)
    public function cambiarEstado(Espacio $espacio)
    {
        if (!$this->puedeGestionar($espacio)) {
            return redirect()->route('espacios.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        $nuevoActivo = !$espacio->activo;

        $espacio->update(['activo' => $nuevoActivo]);

        return back()->with(
            'success',
            $nuevoActivo ? 'Espacio reactivado correctamente' : 'Espacio desactivado correctamente'
        );
    }

    // Solo administrador (la ruta ya lo exige). Si el espacio no se ha usado en ningun bloque ni sesion,
    // se elimina junto con sus asignaciones de encargado; si se ha usado, se desactiva
    public function eliminar(Espacio $espacio, AuditoriaService $auditoria)
    {
        if (HorarioClase::where('espacio_id', $espacio->id)->exists() || Sesion::where('espacio_id', $espacio->id)->exists()) {
            return back()->with('error', 'No se puede eliminar definitivamente: el espacio tiene bloques de clase o sesiones relacionadas. Desactívalo para conservar su historial');
        }

        try {
            DB::transaction(function () use ($espacio, $auditoria) {
                $auditoria->registrar('espacios', $espacio->id, 'eliminacion_definitiva', [
                    'nombre' => $espacio->nombre,
                    'tipo' => $espacio->tipo,
                    'ubicacion' => $espacio->ubicacion,
                    'encargados_asignados' => $espacio->encargados()->count(),
                ]);

                $espacio->encargados()->detach();
                $espacio->delete();
            });
        } catch (QueryException $e) {
            return back()->with('error', 'No se pudo eliminar el espacio porque tiene registros relacionados. Desactívalo en su lugar');
        }

        return redirect()->route('espacios.index')
            ->with('success', 'Espacio eliminado definitivamente');
    }

    // Solo administrador (la ruta ya lo exige)
    public function asignarEncargado(Request $request, Espacio $espacio)
    {
        $request->validate([
            'usuario_id' => [
                'required',
                Rule::exists('usuarios', 'id')->where('rol', 'encargado')->where('activo', 1),
            ],
        ], [
            'usuario_id.required' => 'Selecciona un encargado',
            'usuario_id.exists' => 'Selecciona un encargado activo',
        ]);

        $yaVinculado = $espacio->encargados()->where('usuarios.id', $request->usuario_id)->exists();

        if ($yaVinculado) {
            // Reactiva una asignacion dada de baja antes
            $espacio->encargados()->updateExistingPivot($request->usuario_id, ['activo' => 1]);
        } else {
            $espacio->encargados()->attach($request->usuario_id, ['activo' => 1]);
        }

        return back()->with('success', 'Encargado asignado correctamente');
    }

    // Solo administrador. Baja logica de la asignacion
    public function quitarEncargado(Espacio $espacio, Usuario $usuario)
    {
        $espacio->encargados()->updateExistingPivot($usuario->id, ['activo' => 0]);

        return back()->with('success', 'Encargado desasignado correctamente');
    }

    // El administrador gestiona todo; el encargado solo lo que tiene asignado
    private function puedeGestionar(Espacio $espacio): bool
    {
        $usuario = Auth::user();

        if ($usuario->rol === 'administrador') {
            return true;
        }

        return $usuario->espaciosAsignados()->where('espacios.id', $espacio->id)->exists();
    }

    private function reglas(Request $request, ?Espacio $espacio = null): array
    {
        // No se repite el mismo nombre en la misma ubicacion
        $unico = Rule::unique('espacios', 'nombre')->where('ubicacion', $request->ubicacion);

        if ($espacio) {
            $unico->ignore($espacio->id);
        }

        return [
            'nombre' => ['required', 'string', 'max:100', $unico],
            'tipo' => 'required|in:aula,laboratorio',
            'ubicacion' => 'required|string|max:200',
            'descripcion' => 'nullable|string|max:1000',
            'activo' => 'required|in:0,1',
        ];
    }

    private function mensajes(): array
    {
        return [
            'nombre.unique' => 'Ya existe un espacio con ese nombre en esa ubicación',
        ];
    }
}
