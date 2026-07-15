<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Google OAuth — единственный способ входа
    |--------------------------------------------------------------------------
    |
    | Паролей в системе нет. Scopes запрашиваем только openid/email/profile —
    | это «базовый вход». Держаться его важно по двум причинам: он не требует
    | аудита Google при верификации, и для него не обязательна публичная
    | политика конфиденциальности на этапе Testing. Любой дополнительный scope
    | ломает и то, и другое — не добавлять без крайней нужды.
    |
    | Разработка: статус Testing + redirect на http://127.0.0.1:8000 — домен
    | и юр. страницы для этого не нужны.
    |
    | Публикация: Política de privacidad обязательна, redirect только HTTPS
    | на боевой домен, localhost из прод-клиента убрать.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

];
