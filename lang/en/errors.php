<?php

return [
    // Error pages. No technical detail — it is of no use to a visitor.
    'back_home' => 'Back to home',

    '403' => [
        'title'   => 'Access denied',
        'message' => 'You do not have permission to view this page.',
    ],
    '404' => [
        'title'   => 'Page not found',
        'message' => 'The listing may have been removed, or the address is wrong.',
    ],
    '419' => [
        'title'   => 'Your session has expired',
        'message' => 'Reload the page and try again.',
    ],
    '429' => [
        'title'   => 'Too many requests',
        'message' => 'Please wait a moment before trying again.',
    ],
    '500' => [
        'title'   => 'Server error',
        'message' => 'Something went wrong on our side. We are looking into it.',
    ],
    '503' => [
        'title'   => 'Under maintenance',
        'message' => 'We will be back in a few minutes.',
    ],
];
