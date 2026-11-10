<?php

return [

    // Carpetas del disco privado (storage/app/private) que forman parte de la copia
    'carpetas' => ['evidencias', 'evaluaciones', 'resultados'],

    // Tablas que no se copian ni se reemplazan al restaurar: sesiones, colas, cache, el propio historial de copias
    // y el registro de migraciones (se compara, no se restaura)
    'tablas_excluidas' => [
        'respaldos', 'migrations', 'sessions', 'cache', 'cache_locks',
        'jobs', 'job_batches', 'failed_jobs',
    ],

    // Carpeta (dentro del disco privado) donde se guardan las copias
    'carpeta_destino' => 'respaldos',

    // Version del formato del archivo de copia
    'formato' => 1,

    // Filas por bloque dentro del archivo de copia
    'filas_por_bloque' => 500,

];
