<?php

$esWindows = PHP_OS_FAMILY === 'Windows';

return [

    // Interprete de Python del entorno virtual del proyecto
    'python' => env('PYTHON_BIN', base_path($esWindows ? 'python/.venv/Scripts/python.exe' : 'python/.venv/bin/python')),

    // Script del componente de inferencia
    'script' => base_path('python/contar_personas.py'),

    // Tiempo limite de cada ejecucion completa (YOLO + SAM 3), en segundos
    'timeout' => (int) env('INFERENCIA_TIMEOUT', 900),

    // Cola dedicada a la inferencia (el trabajador debe escucharla)
    'cola' => 'inferencia',

    // Parametros por defecto al registrar una configuracion del modelo
    'parametros' => [
        'clase_id' => 0,
        'etiqueta' => 'person',
        'conf' => 0.25,
        'iou' => 0.7,
        'imgsz' => 640,
        'max_det' => 300,
    ],

    // SAM 3 refina las cajas de YOLO (no cambia el conteo). Se guarda en la configuracion al registrarla.
    'sam' => [
        // Tiempo maximo de SAM 3 dentro de la ejecucion, en segundos (incluye cargar el modelo).
        // Si se pasa, se conserva el resultado de YOLO y se avisa al profesor.
        'timeout_s' => (int) env('SAM_TIMEOUT', 600),

        // Una mascara que cubre menos de esta fraccion de su caja se marca "a revisar"
        'relacion_minima' => 0.25,

        // Dos mascaras que comparten mas de esta fraccion de la mas pequena se marcan "a revisar"
        'solape_maximo' => 0.30,
    ],

];
