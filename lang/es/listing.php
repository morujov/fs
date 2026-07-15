<?php

return [
    'create_title' => 'Vender un número',

    // Дисклеймер обязателен: юридически номер не собственность абонента,
    // продаётся перенос линии. Блюпринт, раздел 0.
    'legal_notice' => 'Anuncio de cesión de línea. La transferencia se realiza mediante portabilidad entre las partes y sus operadores. Este sitio no interviene en la operación.',

    'submit'   => 'Publicar anuncio',
    'my_listings' => 'Mis anuncios',
    'no_listings' => 'Todavía no has publicado ningún anuncio.',

    'submitted_without_otp' => 'Anuncio creado. Pasará a revisión.',

    'attributes' => [
        'msisdn'           => 'número',
        'price'            => 'precio',
        'is_negotiable'    => 'precio negociable',
        'operator_id'      => 'operador',
        'line_type'        => 'tipo de línea',
        'has_permanency'   => 'permanencia',
        'permanency_until' => 'fin de permanencia',
        'condition'        => 'estado',
        'province_id'      => 'provincia',
        'city'             => 'ciudad',
        'description'      => 'comentarios',
        'contact_name'     => 'tu nombre',
        'contact_phone'    => 'tu teléfono de contacto',
        'contact_email'    => 'tu email',
        'contact_whatsapp' => 'WhatsApp',
        'seller_type'      => 'tipo de vendedor',
    ],

    'messages' => [
        'price.required' => 'Indica un precio o marca el anuncio como negociable.',
        'permanency_until.required' => 'Indica hasta cuándo dura la permanencia.',
        'permanency_until.after' => 'La fecha de permanencia debe ser futura.',
    ],

    'validation' => [
        'msisdn_malformed'      => 'El número debe tener 9 dígitos.',
        'msisdn_not_sellable'   => 'Este número no se puede vender en esta plataforma.',
        'msisdn_already_listed' => 'Este número ya tiene un anuncio activo.',
    ],

    // Подписи-подсказки. Без них поля заполняют как попало — «новый/использованный»
    // для номера двусмысленно (пробел №12 блюпринта).
    'help' => [
        'msisdn'        => 'El número que vendes. 9 dígitos, tal y como lo marcarías.',
        'condition_new' => 'Nuevo: nunca se ha activado.',
        'condition_used'=> 'Usado: ha estado en uso.',
        'contact_phone' => 'Tu teléfono para que te contacten. No es el número que vendes. Se muestra oculto hasta que el comprador entra con Google.',
        'permanency'    => 'Si la línea tiene compromiso de permanencia con el operador.',
        'negotiable'    => 'Marca esta casilla si prefieres negociar el precio.',
    ],

    'conditions' => [
        'new'  => 'Nuevo',
        'used' => 'Usado',
    ],

    'line_types' => [
        'prepago'  => 'Prepago',
        'contrato' => 'Contrato',
    ],

    'seller_types' => [
        'private' => 'Particular',
        'shop'    => 'Tienda',
    ],

    'statuses' => [
        'draft'    => 'Borrador',
        'pending'  => 'En revisión',
        'active'   => 'Publicado',
        'rejected' => 'Rechazado',
        'sold'     => 'Vendido',
        'expired'  => 'Caducado',
        'archived' => 'Archivado',
    ],
];
