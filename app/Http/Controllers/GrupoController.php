<?php

namespace App\Http\Controllers;

use App\Models\Grupo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GrupoController extends Controller
{
    public function index(Request $request)
    {
        // clases_vigentes: se usa para advertir al desactivar un grupo
        $consulta = Grupo::query()->withCount(['clases as clases_vigentes' => function ($q) {
            $q->where('vigente', 1);
        }]);

        // Busqueda por clave o programa
        if ($request->filled('buscar')) {
            $buscar = $request->buscar;

            $consulta->where(function ($q) use ($buscar) {
                $q->where('clave', 'like', "%{$buscar}%")
                  ->orWhere('programa', 'like', "%{$buscar}%");
            });
        }

        // Filtros por periodo y estado
        if ($request->filled('periodo')) {
            $consulta->where('periodo', $request->periodo);
        }

        if ($request->filled('estado')) {
            $consulta->where('activo', $request->estado === 'activo' ? 1 : 0);
        }

        $grupos = $consulta->orderByDesc('periodo')->orderBy('clave')->paginate(10)->withQueryString();

        // Periodos existentes, para el filtro
        $periodos = Grupo::select('periodo')->distinct()->orderByDesc('periodo')->pluck('periodo');

        return view('grupos.index', compact('grupos', 'periodos'));
    }

    public function create()
    {
        return view('grupos.create');
    }

    public function store(Request $request)
    {
        $this->normalizar($request);

        $request->validate($this->reglas($request), $this->mensajes());

        Grupo::create([
            'clave' => $request->clave,
            'programa' => $request->programa,
            'cuatrimestre' => $request->cuatrimestre,
            'periodo' => $request->periodo,
            'activo' => $request->activo,
        ]);

        return redirect()->route('grupos.index')
            ->with('success', 'Grupo registrado correctamente');
    }

    public function edit(Grupo $grupo)
    {
        return view('grupos.edit', compact('grupo'));
    }

    public function update(Request $request, Grupo $grupo)
    {
        $this->normalizar($request);

        $request->validate($this->reglas($request, $grupo), $this->mensajes());

        $grupo->update([
            'clave' => $request->clave,
            'programa' => $request->programa,
            'cuatrimestre' => $request->cuatrimestre,
            'periodo' => $request->periodo,
            'activo' => $request->activo,
        ]);

        return redirect()->route('grupos.index')
            ->with('success', 'Grupo actualizado correctamente');
    }

    // Baja logica: desactiva o reactiva el grupo (nunca se elimina el registro)
    public function cambiarEstado(Grupo $grupo)
    {
        $nuevoActivo = !$grupo->activo;

        $grupo->update(['activo' => $nuevoActivo]);

        return back()->with(
            'success',
            $nuevoActivo ? 'Grupo reactivado correctamente' : 'Grupo desactivado correctamente'
        );
    }

    // Clave y periodo se guardan en mayusculas para que no haya variantes del mismo valor
    private function normalizar(Request $request): void
    {
        $request->merge([
            'clave' => Str::upper(trim((string) $request->clave)),
            'periodo' => Str::upper(trim((string) $request->periodo)),
        ]);
    }

    private function reglas(Request $request, ?Grupo $grupo = null): array
    {
        // Una misma clave no se repite dentro del mismo periodo
        $unico = Rule::unique('grupos', 'clave')->where('periodo', $request->periodo);

        if ($grupo) {
            $unico->ignore($grupo->id);
        }

        return [
            'clave' => ['required', 'string', 'max:30', $unico],
            'programa' => 'required|string|max:150',
            'cuatrimestre' => 'required|integer|min:1|max:255',
            'periodo' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9\-]+$/'],
            'activo' => 'required|in:0,1',
        ];
    }

    private function mensajes(): array
    {
        return [
            'clave.unique' => 'Ya existe un grupo con esa clave en ese periodo',
            'periodo.regex' => 'El periodo solo puede tener letras, números y guiones. Ejemplo: O2026',
        ];
    }
}
