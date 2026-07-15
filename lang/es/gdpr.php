<?php

return [
    'title'  => 'Mis datos',
    'intro'  => 'Aquí puedes ver qué guardamos sobre ti, descargarlo o borrar tu cuenta.',

    'export_title' => 'Descargar mis datos',
    'export_help'  => 'Un archivo JSON con todo lo que tenemos asociado a tu cuenta.',
    'export_notice' => 'Estos son todos los datos personales asociados a tu cuenta en el momento de la exportación.',
    'export_button' => 'Descargar (JSON)',

    'delete_title' => 'Borrar mi cuenta',

    // Честность до кнопки, а не после. «Мы всё удалим», а потом «ну кроме
    // вот этого» — хуже, чем сразу объяснить.
    'delete_help'  => 'Es irreversible. Esto es exactamente lo que pasará:',
    'delete_what' => [
        'account'  => 'Tu cuenta se borra: nombre, email, teléfono, avatar.',
        'listings' => 'Tus anuncios se retiran y se les quitan los datos de contacto.',
        'personal' => 'Tus búsquedas guardadas y favoritos se borran.',
    ],
    'delete_kept_title' => 'Qué se queda, y por qué:',
    'delete_kept' => [
        'listings' => 'El histórico de anuncios sigue, pero sin tu nombre ni tus contactos: son datos de mercado, ya no tuyos.',
        'reports'  => 'Si alguien denunció un anuncio tuyo, la denuncia se queda (sin tu nombre). El RGPD lo permite: puede ser prueba en una reclamación de otra persona, y borrarla le haría daño a ella.',
    ],
    'delete_can_return' => 'Puedes volver a registrarte con el mismo Google cuando quieras.',

    'confirm_label' => 'Escribe BORRAR para confirmar',
    'confirm_error' => 'Escribe BORRAR exactamente para confirmar.',
    'delete_button' => 'Borrar mi cuenta definitivamente',

    'erased' => 'Tu cuenta ha sido borrada.',

    'stored_title' => 'Qué guardamos ahora mismo',
    'stored' => [
        'active_listings' => 'Anuncios activos',
        'total_listings'  => 'Anuncios en total',
        'reveals'         => 'Contactos que has visto',
        'saved_searches'  => 'Búsquedas guardadas',
        'favorites'       => 'Favoritos',
    ],
];
