<?php

return [
    // Текст SMS. Держать коротким: SMS режется на 160 символов, а каждый
    // сегмент — отдельные деньги.
    'sms_text' => 'Tu código de verificación para Números ES es :code. Caduca en :minutes minutos.',

    'title'    => 'Verifica tu número',
    'intro'    => 'Hemos enviado un código de 6 dígitos por SMS al número :number. Introdúcelo para publicar el anuncio.',

    // Объясняем, зачем это, иначе шаг выглядит бюрократией и продавцы уходят.
    'why'      => 'Verificamos que el número es tuyo. Así evitamos que alguien publique un número ajeno.',

    'code'     => 'Código',
    'verify'   => 'Verificar',
    'resend'   => 'Reenviar código',

    'verified' => 'Número verificado. El anuncio pasará a revisión.',
    'resent'   => 'Te hemos enviado un código nuevo.',

    'attributes' => [
        'code' => 'código',
    ],

    'errors' => [
        'not_found'         => 'No hay ningún código pendiente. Solicita uno nuevo.',
        'expired'           => 'El código ha caducado. Solicita uno nuevo.',
        'invalid'           => 'Código incorrecto. Te quedan :left intentos.',
        'too_many_attempts' => 'Has agotado los :max intentos. Solicita un código nuevo.',
        'too_many_sends'    => 'Has alcanzado el límite de :max envíos para este número.',
    ],
];
