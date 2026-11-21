<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PanelController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\RecuperacionController;
use App\Http\Controllers\EspacioController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\ClaseController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\SesionController;
use App\Http\Controllers\EvidenciaController;
use App\Http\Controllers\PaseListaController;
use App\Http\Controllers\ProcesamientoController;
use App\Http\Controllers\RevisionController;
use App\Http\Controllers\ActivacionController;
use App\Http\Controllers\EstimacionController;
use App\Http\Controllers\EvaluacionController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\RespaldoController;

Route::get('/', function () {
    return redirect()->route('acceso');
});

Route::get('/acceso', [
    AuthController::class, 'loginForm'
])->name('acceso');

Route::post('/acceso', [
    AuthController::class, 'login'
])->name('acceso.store');

Route::post('/salir', [
    AuthController::class, 'logout'
])->name('salir');

Route::get('/olvide-contrasena', [
    RecuperacionController::class, 'solicitudForm'
])->name('recuperacion.solicitud');

Route::post('/olvide-contrasena', [
    RecuperacionController::class, 'enviarEnlace'
])->middleware('throttle:5,1')->name('recuperacion.enviar');

Route::get('/restablecer-contrasena/{token}', [
    RecuperacionController::class, 'restablecerForm'
])->name('recuperacion.formulario');

Route::post('/restablecer-contrasena', [
    RecuperacionController::class, 'restablecer'
])->middleware('throttle:5,1')->name('recuperacion.restablecer');

Route::get('/activar-cuenta/{token}', [
    ActivacionController::class, 'formulario'
])->name('activacion.formulario');

Route::post('/activar-cuenta', [
    ActivacionController::class, 'activar'
])->middleware('throttle:5,1')->name('activacion.activar');

Route::middleware(['usuario'])->group(function () {

    Route::get('/panel', [
        PanelController::class, 'index'
    ])->name('panel');

    Route::get('/cuenta/contrasena', [
        CuentaController::class, 'passwordForm'
    ])->name('cuenta.password');

    Route::put('/cuenta/contrasena', [
        CuentaController::class, 'passwordUpdate'
    ])->name('cuenta.password.update');

});

Route::middleware(['usuario', 'rol:administrador'])->group(function () {

    Route::get('/usuarios', [
        UsuarioController::class, 'index'
    ])->name('usuarios.index');

    Route::get('/usuarios/create', [
        UsuarioController::class, 'create'
    ])->name('usuarios.create');

    Route::post('/usuarios', [
        UsuarioController::class, 'store'
    ])->name('usuarios.store');

    Route::get('/usuarios/{usuario}/edit', [
        UsuarioController::class, 'edit'
    ])->name('usuarios.edit');

    Route::put('/usuarios/{usuario}', [
        UsuarioController::class, 'update'
    ])->name('usuarios.update');

    Route::patch('/usuarios/{usuario}/estado', [
        UsuarioController::class, 'cambiarEstado'
    ])->name('usuarios.estado');

    Route::delete('/usuarios/{usuario}', [
        UsuarioController::class, 'eliminar'
    ])->name('usuarios.eliminar');

        Route::post('/usuarios/{usuario}/activacion', [
        UsuarioController::class, 'reenviarActivacion'
    ])->name('usuarios.activacion');

    Route::get('/evaluaciones', [
        EvaluacionController::class, 'index'
    ])->name('evaluaciones.index');

    Route::get('/evaluaciones/nueva', [
        EvaluacionController::class, 'create'
    ])->name('evaluaciones.create');

    Route::get('/evaluaciones/exportar', [
        EvaluacionController::class, 'exportar'
    ])->name('evaluaciones.exportar');

    Route::post('/evaluaciones', [
        EvaluacionController::class, 'store'
    ])->name('evaluaciones.store');

    Route::get('/evaluaciones/{evaluacion}', [
        EvaluacionController::class, 'show'
    ])->name('evaluaciones.show');

    Route::put('/evaluaciones/{evaluacion}', [
        EvaluacionController::class, 'update'
    ])->name('evaluaciones.update');

    Route::post('/evaluaciones/{evaluacion}/analizar', [
        EvaluacionController::class, 'analizar'
    ])->name('evaluaciones.analizar');

    Route::get('/evaluaciones/{evaluacion}/imagen', [
        EvaluacionController::class, 'imagen'
    ])->name('evaluaciones.imagen');

    Route::get('/evaluaciones/{evaluacion}/detecciones', [
        EvaluacionController::class, 'anotada'
    ])->name('evaluaciones.anotada');

    Route::get('/respaldos', [
        RespaldoController::class, 'index'
    ])->name('respaldos.index');

    Route::post('/respaldos', [
        RespaldoController::class, 'store'
    ])->name('respaldos.store');

    Route::get('/respaldos/{respaldo}/descargar', [
        RespaldoController::class, 'descargar'
    ])->name('respaldos.descargar');

    Route::get('/respaldos/{respaldo}/restaurar', [
        RespaldoController::class, 'confirmar'
    ])->name('respaldos.confirmar');
    
    Route::post('/respaldos/{respaldo}/restaurar', [
        RespaldoController::class, 'restaurar'
    ])->name('respaldos.restaurar');
    
    Route::post('/espacios/{espacio}/encargados', [
        EspacioController::class, 'asignarEncargado'
    ])->name('espacios.encargados.asignar');

    Route::delete('/espacios/{espacio}/encargados/{usuario}', [
        EspacioController::class, 'quitarEncargado'
    ])->name('espacios.encargados.quitar');

    Route::delete('/espacios/{espacio}', [
        EspacioController::class, 'eliminar'
    ])->name('espacios.eliminar');

        Route::get('/grupos', [
        GrupoController::class, 'index'
    ])->name('grupos.index');

    Route::get('/grupos/create', [
        GrupoController::class, 'create'
    ])->name('grupos.create');

    Route::post('/grupos', [
        GrupoController::class, 'store'
    ])->name('grupos.store');

    Route::get('/grupos/{grupo}/edit', [
        GrupoController::class, 'edit'
    ])->name('grupos.edit');

    Route::put('/grupos/{grupo}', [
        GrupoController::class, 'update'
    ])->name('grupos.update');

    Route::patch('/grupos/{grupo}/estado', [
        GrupoController::class, 'cambiarEstado'
    ])->name('grupos.estado');

    Route::delete('/grupos/{grupo}', [
        GrupoController::class, 'eliminar'
    ])->name('grupos.eliminar');

    

});

Route::middleware(['usuario', 'rol:administrador,encargado'])->group(function () {

    Route::get('/espacios', [
        EspacioController::class, 'index'
    ])->name('espacios.index');

    Route::get('/espacios/create', [
        EspacioController::class, 'create'
    ])->name('espacios.create');

    Route::post('/espacios', [
        EspacioController::class, 'store'
    ])->name('espacios.store');

    Route::get('/espacios/{espacio}/edit', [
        EspacioController::class, 'edit'
    ])->name('espacios.edit');

    Route::put('/espacios/{espacio}', [
        EspacioController::class, 'update'
    ])->name('espacios.update');

    Route::patch('/espacios/{espacio}/estado', [
        EspacioController::class, 'cambiarEstado'
    ])->name('espacios.estado');

});

Route::middleware(['usuario', 'rol:profesor'])->group(function () {

    Route::get('/clases', [
        ClaseController::class, 'index'
    ])->name('clases.index');

    Route::get('/clases/create', [
        ClaseController::class, 'create'
    ])->name('clases.create');

    Route::post('/clases', [
        ClaseController::class, 'store'
    ])->name('clases.store');

    Route::get('/clases/{clase}/edit', [
        ClaseController::class, 'edit'
    ])->name('clases.edit');

    Route::put('/clases/{clase}', [
        ClaseController::class, 'update'
    ])->name('clases.update');

    Route::patch('/clases/{clase}/estado', [
        ClaseController::class, 'cambiarEstado'
    ])->name('clases.estado');

    Route::delete('/clases/{clase}', [
        ClaseController::class, 'eliminar'
    ])->name('clases.eliminar');

        Route::get('/sesiones', [
        SesionController::class, 'index'
    ])->name('sesiones.index');

    Route::get('/sesiones/create', [
        SesionController::class, 'create'
    ])->name('sesiones.create');

    Route::post('/sesiones', [
        SesionController::class, 'store'
    ])->name('sesiones.store');

    Route::get('/sesiones/{sesion}', [
        SesionController::class, 'show'
    ])->name('sesiones.show');

    Route::get('/sesiones/{sesion}/edit', [
        SesionController::class, 'edit'
    ])->name('sesiones.edit');

    Route::put('/sesiones/{sesion}', [
        SesionController::class, 'update'
    ])->name('sesiones.update');

    Route::post('/sesiones/{sesion}/cancelar', [
        SesionController::class, 'cancelar'
    ])->name('sesiones.cancelar');

    Route::post('/sesiones/{sesion}/reactivar', [
        SesionController::class, 'reactivar'
    ])->name('sesiones.reactivar');

    Route::post('/sesiones/{sesion}/asistencia', [
        AsistenciaController::class, 'guardar'
    ])->name('sesiones.asistencia.guardar');

    Route::post('/sesiones/{sesion}/asistencia/reabrir', [
        AsistenciaController::class, 'reabrir'
    ])->name('sesiones.asistencia.reabrir');

        Route::post('/sesiones/{sesion}/pases', [
        SesionController::class, 'programarPases'
    ])->name('sesiones.pases.programar');

    Route::get('/sesiones/{sesion}/pases/{pase}/revision', [
        RevisionController::class, 'show'
        ])->name('sesiones.revision');

    Route::post('/sesiones/{sesion}/pases/{pase}/revision', [
        RevisionController::class, 'guardar'
        ])->name('sesiones.revision.guardar');

    Route::post('/sesiones/{sesion}/estimacion', [
        EstimacionController::class, 'calcular'
        ])->name('sesiones.estimacion');

});

Route::middleware(['usuario', 'rol:encargado'])->group(function () {

    Route::get('/pases', [
        PaseListaController::class, 'index'
    ])->name('pases.index');

    Route::get('/pases/{sesion}', [
        PaseListaController::class, 'show'
    ])->name('pases.show');

    Route::post('/pases/{sesion}/evidencias', [
        PaseListaController::class, 'subir'
    ])->name('pases.subir');

    Route::post('/pases/{pase}/visibilidad', [
        PaseListaController::class, 'marcarVisibilidad'
    ])->name('pases.visibilidad');

        Route::post('/pases/{pase}/analizar', [
        PaseListaController::class, 'analizar'
    ])->name('pases.analizar');

});

Route::middleware(['usuario', 'rol:encargado,profesor'])->group(function () {

    Route::get('/evidencias/{evidencia}/archivo', [
        EvidenciaController::class, 'archivo'
    ])->name('evidencias.archivo');

        Route::get('/procesamientos/{procesamiento}/anotada', [
        ProcesamientoController::class, 'anotada'
    ])->name('procesamientos.anotada');

});

Route::middleware(['usuario', 'rol:administrador,encargado,profesor'])->group(function () {

    Route::get('/reportes', [
        ReporteController::class, 'index'
    ])->name('reportes.index');

    Route::get('/reportes/clase', [
        ReporteController::class, 'clase'
    ])->name('reportes.clase');

    Route::get('/reportes/momentos', [
        ReporteController::class, 'momentos'
    ])->name('reportes.momentos');

    Route::get('/reportes/grupos', [
        ReporteController::class, 'grupos'
    ])->name('reportes.grupos');

    Route::get('/reportes/incidencias', [
        ReporteController::class, 'incidencias'
    ])->name('reportes.incidencias');

    Route::post('/notificaciones/leer-todas', [
        NotificacionController::class, 'leerTodas'
    ])->name('notificaciones.leerTodas');

    Route::get('/notificaciones', [
        NotificacionController::class, 'index'
    ])->name('notificaciones.index');

    Route::post('/notificaciones/{notificacion}/leer', [
        NotificacionController::class, 'leer'
    ])->name('notificaciones.leer');
    
    Route::post('/notificaciones/{notificacion}/abrir', [
        NotificacionController::class, 'abrir'
    ])->name('notificaciones.abrir');

});