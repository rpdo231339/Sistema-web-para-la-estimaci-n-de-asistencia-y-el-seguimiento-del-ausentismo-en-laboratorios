<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class CuentaController extends Controller
{
    public function passwordForm()
    {
        return view('cuenta.password');
    }

    public function passwordUpdate(Request $request)
    {
        $request->validate([
            'password_actual' => 'required|current_password',
            'password' => 'required|string|min:8|confirmed|different:password_actual',
        ]);

        $usuario = Auth::user();

        $usuario->update([
            'password' => Hash::make($request->password),
            'version_seguridad' => $usuario->version_seguridad + 1,
        ]);

        // Las demas sesiones de esta cuenta se cierran; esta sigue valida
        $request->session()->put('version_seguridad', $usuario->version_seguridad);
        $request->session()->regenerate();

        return redirect()->route('panel')
            ->with('success', 'Contraseña actualizada correctamente');
    }
}
