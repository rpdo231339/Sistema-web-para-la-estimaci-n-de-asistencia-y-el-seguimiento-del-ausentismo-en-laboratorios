<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Support\Facades\Auth;

class NotificacionController extends Controller
{
    // Bandeja interna: solo las notificaciones propias y vigentes
    public function index()
    {
        $notificaciones = Notificacion::where('usuario_id', Auth::id())
            ->where('vigente', 1)
            ->where('programada_en', '<=', now())
            ->orderByDesc('id')
            ->paginate(20);

        return view('notificaciones.index', compact('notificaciones'));
    }

    public function leer(Notificacion $notificacion)
    {
        $this->propia($notificacion);
        $this->marcarLeida($notificacion);

        return back();
    }

    // Marca como leida y lleva a la pantalla relacionada
    public function abrir(Notificacion $notificacion)
    {
        $this->propia($notificacion);
        $this->marcarLeida($notificacion);

        $ruta = $notificacion->datos['ruta'] ?? null;

        // Solo rutas internas: nunca una direccion externa
        if (is_string($ruta) && str_starts_with($ruta, '/') && !str_starts_with($ruta, '//')) {
            return redirect($ruta);
        }

        return redirect()->route('notificaciones.index');
    }

    public function leerTodas()
    {
        Notificacion::where('usuario_id', Auth::id())->whereNull('leida_en')->update(['leida_en' => now()]);

        return back()->with('success', 'Todas las notificaciones quedaron como leídas');
    }

    // Una notificacion ajena responde igual que una inexistente
    private function propia(Notificacion $notificacion): void
    {
        if ((int) $notificacion->usuario_id !== (int) Auth::id()) {
            abort(404);
        }
    }

    private function marcarLeida(Notificacion $notificacion): void
    {
        if (!$notificacion->leida_en) {
            $notificacion->update(['leida_en' => now()]);
        }
    }
}
