<?php

return [
    // Причины отказа и задержки. Их видит продавец в своём кабинете,
    // поэтому пишем по-человечески: что не так и что с этим делать.
    'reasons' => [
        'msisdn_malformed'      => 'El número debe tener 9 dígitos.',
        'msisdn_not_sellable'   => ':reason',
        'blocklisted'           => 'Este número no se puede publicar. Si crees que es un error, escríbenos.',
        'duplicate'             => 'Este número ya tiene un anuncio activo. Solo puede haber uno.',
        'price_missing'         => 'Indica un precio o marca el anuncio como negociable.',
        'price_out_of_range'    => 'El precio debe estar entre :min € y :max €.',

        'contact_phone_missing'   => 'Falta tu teléfono de contacto.',
        'contact_phone_malformed' => 'Tu teléfono de contacto no parece válido.',
        'contact_equals_msisdn'   => 'Tu teléfono de contacto es el mismo número que vendes. Si lo vendes, los compradores no podrán localizarte. Indica otro.',
        'contact_email_malformed' => 'Tu email no parece válido.',
        'contact_email_disposable' => 'El email parece temporal. Usa uno que puedas leer dentro de unas semanas.',

        'otp_pending' => 'Verifica tu número con el código que te hemos enviado por SMS.',

        'contacts_in_text'   => 'Parece que has puesto datos de contacto en la descripción. Un revisor lo comprobará.',
        'forbidden_content'  => 'La descripción necesita una revisión manual.',
        'rate_limit'         => 'Has publicado varios anuncios en poco tiempo (:max al día). Un revisor lo comprobará.',
        'account_too_new'    => 'Tu cuenta es muy reciente. Tu primer anuncio pasa por revisión.',
        'shop_unverified'    => 'Tu tienda está pendiente de verificación. Publicaremos el anuncio cuando la validemos.',
        'shop_missing'       => 'Has marcado «tienda» pero no has registrado ninguna. Regístrala para publicar.',

        'manual_review'      => 'Tu anuncio está en revisión. Normalmente tarda poco.',
    ],

    // Названия правил для админки — их видит модератор, не продавец.
    'rules' => [
        'number_sellable'   => 'Rango de numeración',
        'blocklist'         => 'Lista de bloqueo',
        'duplicate'         => 'Duplicado activo',
        'price_range'       => 'Rango de precio',
        'contacts_valid'    => 'Contactos válidos',
        'otp_verified'      => 'Número verificado (OTP)',
        'contacts_in_text'  => 'Contactos en la descripción',
        'forbidden_content' => 'Contenido prohibido',
        'rate_limit'        => 'Ritmo de publicación',
        'account_age'       => 'Antigüedad de la cuenta',
        'shop_verified'     => 'Tienda verificada',
    ],

    'outcomes' => [
        'pass'   => 'Sin incidencias',
        'flag'   => 'Sospechoso',
        'reject' => 'Rechazado',
        'hold'   => 'A la espera',
    ],
];
