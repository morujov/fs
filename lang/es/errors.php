<?php

return [
    // Páginas de error. Sin detalles técnicos: al visitante no le sirven.
    'back_home' => 'Volver al inicio',

    '403' => [
        'title'   => 'Acceso denegado',
        'message' => 'No tienes permiso para ver esta página.',
    ],
    '404' => [
        'title'   => 'Página no encontrada',
        'message' => 'El anuncio puede haber sido retirado o la dirección es incorrecta.',
    ],
    '419' => [
        'title'   => 'La sesión ha caducado',
        'message' => 'Vuelve a cargar la página e inténtalo de nuevo.',
    ],
    '429' => [
        'title'   => 'Demasiadas solicitudes',
        'message' => 'Espera unos momentos antes de volver a intentarlo.',
    ],
    '500' => [
        'title'   => 'Error del servidor',
        'message' => 'Algo ha fallado por nuestra parte. Ya lo estamos revisando.',
    ],
    '503' => [
        'title'   => 'En mantenimiento',
        'message' => 'Volvemos en unos minutos.',
    ],
];
