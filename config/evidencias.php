<?php

return [

    // Tamano maximo por archivo, en KB (15 MB)
    'max_kb' => (int) env('EVIDENCIA_MAX_KB', 15360),

    // Rechaza imagenes cuya hora representada este en el futuro (con 5 min de margen).
    // Ponlo en false en .env (EVIDENCIA_RECHAZAR_FUTURO=false) solo mientras pruebas
    // simulando una clase con fecha futura; en uso real debe estar en true.
    'rechazar_futuro' => (bool) env('EVIDENCIA_RECHAZAR_FUTURO', true),

    // Tipos admitidos: el contenido real se comprueba en el servidor.
    // La clave es el tipo MIME y el valor la extension con la que se guarda.
    'mimes' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ],

];
