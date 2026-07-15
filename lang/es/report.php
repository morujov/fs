<?php

return [
    'title'  => 'Denunciar anuncio',
    'intro'  => 'Cuéntanos qué pasa con este anuncio. No hace falta tener cuenta.',
    'submit' => 'Enviar denuncia',
    'thanks' => 'Gracias. Un revisor lo comprobará.',

    'attributes' => [
        'reason'         => 'motivo',
        'comment'        => 'comentario',
        'reporter_email' => 'tu email',
    ],

    'reasons' => [
        'not_mine'   => 'Es mi número y yo no lo vendo',
        'fraud'      => 'Creo que es una estafa',
        'wrong_info' => 'Los datos no son correctos',
        'spam'       => 'Es spam o publicidad',
        'sold'       => 'Ya está vendido',
        'other'      => 'Otro motivo',
    ],

    'comment_help' => 'Opcional, pero nos ayuda a entender el caso.',
    'email_help'   => 'Opcional. Solo si quieres que te contemos qué hemos hecho.',
];
