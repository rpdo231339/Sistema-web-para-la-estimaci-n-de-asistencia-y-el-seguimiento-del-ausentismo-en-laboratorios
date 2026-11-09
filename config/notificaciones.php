<?php

return [

    // Minutos antes de cada momento en que se avisa al encargado
    'anticipacion_minutos' => (int) env('NOTIFICACION_ANTICIPACION_MIN', 2),

    // Intentos maximos de envio por correo de una misma notificacion
    'max_intentos' => (int) env('NOTIFICACION_MAX_INTENTOS', 3),

];
