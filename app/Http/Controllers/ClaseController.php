<?php

namespace App\Http\Controllers;

use App\Models\Clase;
use App\Models\Espacio;
use App\Models\Grupo;
use App\Models\HorarioClase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClaseController extends Controller
{
    private const MENSAJE_SIN_PERMISO = 'No tienes permiso para gestionar esa clase';

    public function index(Request $request)
    {
        $profesorId = Auth::id();

        // El profesor solo ve sus propias clases
        $consulta = Clase::with(['grupo', 'horariosActivos.espacio'])
            ->where('profesor_id', $profesorId);

        if ($request->filled('buscar')) {
            $buscar = $request->buscar;
            $consulta->where('asignatura', 'like', "%{$buscar}%");
        }

        if ($request->filled('periodo')) {
            $periodo = $request->periodo;

            $consulta->whereHas('grupo', function ($q) use ($periodo) {
                $q->where('periodo', $periodo);
            });
        }

        if ($request->filled('estado')) {
            $consulta->where('vigente', $request->estado === 'vigente' ? 1 : 0);
        }

        $clases = $consulta->orderBy('asignatura')->paginate(10)->withQueryString();

        // Periodos de los grupos que usa este profesor, para el filtro
        $periodos = Grupo::whereIn('id', Clase::where('profesor_id', $profesorId)->select('grupo_id'))
            ->select('periodo')
            ->distinct()
            ->orderByDesc('periodo')
            ->pluck('periodo');

        return view('clases.index', compact('clases', 'periodos'));
    }

    public function create()
    {
        // Solo se ofrecen catalogos activos
        $grupos = Grupo::where('activo', 1)->orderByDesc('periodo')->orderBy('clave')->get();
        $espacios = Espacio::where('activo', 1)->orderBy('nombre')->get();

        return view('clases.create', compact('grupos', 'espacios'));
    }

    public function store(Request $request)
    {
        $request->validate($this->reglas(), $this->mensajes(), $this->atributos());

        $this->validarBloques($request);

        DB::transaction(function () use ($request) {
            // El profesor titular es siempre el usuario autenticado
            $clase = Clase::create([
                'profesor_id' => Auth::id(),
                'grupo_id' => $request->grupo_id,
                'asignatura' => $request->asignatura,
                'inscritos' => $request->inscritos,
                'observaciones' => $request->observaciones,
                'vigente' => 1,
            ]);

            foreach ($request->horarios as $bloque) {
                $clase->horarios()->create([
                    'espacio_id' => $bloque['espacio_id'],
                    'dia' => $bloque['dia'],
                    'hora_inicio' => $bloque['hora_inicio'],
                    'hora_fin' => $bloque['hora_fin'],
                    'activo' => 1,
                ]);
            }
        });

        return redirect()->route('clases.index')
            ->with('success', 'Clase registrada correctamente');
    }

    public function edit(Clase $clase)
    {
        if (!$this->esTitular($clase)) {
            return redirect()->route('clases.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        $clase->load('horariosActivos');

        // Catalogos activos, mas lo que la clase ya usa aunque se haya desactivado
        $grupos = Grupo::where(function ($q) use ($clase) {
                $q->where('activo', 1)->orWhere('id', $clase->grupo_id);
            })
            ->orderByDesc('periodo')->orderBy('clave')->get();

        $espacios = Espacio::where(function ($q) use ($clase) {
                $q->where('activo', 1)->orWhereIn('id', $clase->horariosActivos->pluck('espacio_id'));
            })
            ->orderBy('nombre')->get();

        $filasIniciales = $clase->horariosActivos->map(function ($h) {
            return [
                'id' => $h->id,
                'dia' => $h->dia,
                'hora_inicio' => substr($h->hora_inicio, 0, 5),
                'hora_fin' => substr($h->hora_fin, 0, 5),
                'espacio_id' => $h->espacio_id,
            ];
        })->values()->all();

        return view('clases.edit', compact('clase', 'grupos', 'espacios', 'filasIniciales'));
    }

    public function update(Request $request, Clase $clase)
    {
        if (!$this->esTitular($clase)) {
            return redirect()->route('clases.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        $request->validate($this->reglas($clase), $this->mensajes(), $this->atributos());

        $this->validarBloques($request, $clase);

        DB::transaction(function () use ($request, $clase) {
            // La titularidad no cambia: profesor_id no se toca
            $clase->update([
                'grupo_id' => $request->grupo_id,
                'asignatura' => $request->asignatura,
                'inscritos' => $request->inscritos,
                'observaciones' => $request->observaciones,
            ]);

            $existentes = $clase->horariosActivos()->get()->keyBy('id');
            $conservados = [];

            foreach ($request->horarios as $bloque) {
                $datos = [
                    'espacio_id' => $bloque['espacio_id'],
                    'dia' => $bloque['dia'],
                    'hora_inicio' => $bloque['hora_inicio'],
                    'hora_fin' => $bloque['hora_fin'],
                ];

                if (!empty($bloque['id']) && $existentes->has((int) $bloque['id'])) {
                    $existentes[(int) $bloque['id']]->update($datos);
                    $conservados[] = (int) $bloque['id'];
                } else {
                    $clase->horarios()->create($datos + ['activo' => 1]);
                }
            }

            // Los bloques que ya no vienen en el formulario se dan de baja, no se borran
            $quitados = $existentes->keys()->diff($conservados);

            if ($quitados->isNotEmpty()) {
                $clase->horarios()->whereIn('id', $quitados->all())->update(['activo' => 0]);
            }
        });

        return redirect()->route('clases.index')
            ->with('success', 'Clase actualizada correctamente');
    }

    // Baja logica: desactiva o reactiva la clase (nunca se elimina el registro)
    public function cambiarEstado(Clase $clase)
    {
        if (!$this->esTitular($clase)) {
            return redirect()->route('clases.index')->with('error', self::MENSAJE_SIN_PERMISO);
        }

        $nuevoVigente = !$clase->vigente;

        $clase->update(['vigente' => $nuevoVigente]);

        return back()->with(
            'success',
            $nuevoVigente ? 'Clase reactivada correctamente' : 'Clase desactivada correctamente'
        );
    }

    private function esTitular(Clase $clase): bool
    {
        return (int) $clase->profesor_id === (int) Auth::id();
    }

    private function reglas(?Clase $clase = null): array
    {
        // El grupo debe estar activo; al editar se acepta el que la clase ya tiene
        $grupoValido = Rule::exists('grupos', 'id')->where(function ($q) use ($clase) {
            $q->where(function ($g) use ($clase) {
                $g->where('activo', 1);

                if ($clase) {
                    $g->orWhere('id', $clase->grupo_id);
                }
            });
        });

        return [
            'asignatura' => 'required|string|max:150',
            'grupo_id' => ['required', $grupoValido],
            'inscritos' => 'required|integer|min:1',
            'observaciones' => 'nullable|string|max:1000',
            'horarios' => 'required|array|min:1',
            'horarios.*.id' => 'nullable|integer',
            'horarios.*.dia' => 'required|integer|between:1,7',
            'horarios.*.hora_inicio' => 'required|date_format:H:i',
            'horarios.*.hora_fin' => 'required|date_format:H:i|after:horarios.*.hora_inicio',
            'horarios.*.espacio_id' => ['required', 'integer', Rule::exists('espacios', 'id')],
        ];
    }

    // Reglas entre bloques y catalogos que no se pueden expresar con una regla simple
    private function validarBloques(Request $request, ?Clase $clase = null): void
    {
        $bloques = collect($request->horarios)->values();
        $errores = [];

        // Espacios activos; al editar tambien los que la clase ya usa
        $permitidos = Espacio::where('activo', 1)->pluck('id')->map(fn ($v) => (int) $v);

        if ($clase) {
            $permitidos = $permitidos->merge(
                $clase->horariosActivos()->pluck('espacio_id')->map(fn ($v) => (int) $v)
            );
        }

        $idsDeLaClase = $clase ? $clase->horariosActivos()->pluck('id')->map(fn ($v) => (int) $v) : collect();

        foreach ($bloques as $i => $bloque) {
            $numero = $i + 1;

            if (!$permitidos->contains((int) $bloque['espacio_id'])) {
                $errores["horarios.$i.espacio_id"] = "El espacio del bloque $numero está inactivo";
            }

            if (!empty($bloque['id']) && !$idsDeLaClase->contains((int) $bloque['id'])) {
                $errores["horarios.$i.id"] = "El bloque $numero no pertenece a esta clase";
            }
        }

        // Dos bloques de la misma clase no pueden traslaparse el mismo dia
        foreach ($bloques->groupBy('dia') as $dia => $delDia) {
            $ordenados = $delDia->sortBy('hora_inicio')->values();

            for ($k = 1; $k < $ordenados->count(); $k++) {
                if ($ordenados[$k]['hora_inicio'] < $ordenados[$k - 1]['hora_fin']) {
                    $errores['horarios'] = 'Hay bloques que se traslapan el ' . mb_strtolower(HorarioClase::DIAS[(int) $dia]);
                }
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    private function mensajes(): array
    {
        return [
            'grupo_id.exists' => 'Selecciona un grupo activo',
            'inscritos.integer' => 'El total de inscritos debe ser un número entero',
            'inscritos.min' => 'El total de inscritos debe ser mayor que cero',
            'horarios.required' => 'Registra al menos un bloque de horario',
            'horarios.min' => 'Registra al menos un bloque de horario',
            'horarios.*.hora_fin.after' => 'En el bloque :position, la hora de fin debe ser posterior a la de inicio',
        ];
    }

    private function atributos(): array
    {
        return [
            'grupo_id' => 'grupo',
            'inscritos' => 'total de inscritos',
            'horarios' => 'horario',
            'horarios.*.dia' => 'día del bloque :position',
            'horarios.*.hora_inicio' => 'hora de inicio del bloque :position',
            'horarios.*.hora_fin' => 'hora de fin del bloque :position',
            'horarios.*.espacio_id' => 'espacio del bloque :position',
        ];
    }
}
