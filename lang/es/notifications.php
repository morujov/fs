<?php

return [
    'greeting' => '¡Hola, :name!',

    'expiring_soon' => [
        'subject'    => 'Tu anuncio del :number caduca pronto',
        'line'       => 'Tu anuncio del número :number caduca en :days días. Si sigue disponible, renuévalo con un clic.',
        'action'     => 'Renovar anuncio',
        'sold_hint'  => '¿Ya lo has vendido? Márcalo como vendido en tu panel y liberarás el número.',
    ],

    'expired' => [
        'subject' => 'Tu anuncio del :number ha caducado',
        'line'    => 'Tu anuncio del número :number ha caducado y ya no aparece en la web. Puedes reactivarlo cuando quieras.',
        'action'  => 'Reactivar anuncio',
    ],
];
