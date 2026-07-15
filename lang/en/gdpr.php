<?php

return [
    'title' => 'My data',
    'intro' => 'Here you can see what we store about you, download it, or delete your account.',

    'export_title'  => 'Download my data',
    'export_help'   => 'A JSON file with everything we have linked to your account.',
    'export_notice' => 'This is all the personal data linked to your account at the time of export.',
    'export_button' => 'Download (JSON)',

    'delete_title' => 'Delete my account',
    'delete_help'  => 'This cannot be undone. Here is exactly what happens:',
    'delete_what' => [
        'account'  => 'Your account is deleted: name, email, phone, avatar.',
        'listings' => 'Your listings are withdrawn and their contact details removed.',
        'personal' => 'Your saved searches and favourites are deleted.',
    ],
    'delete_kept_title' => 'What stays, and why:',
    'delete_kept' => [
        'listings' => 'The listing history stays, but without your name or contacts: it is market data, no longer yours.',
        'reports'  => 'If someone reported a listing of yours, the report stays (without your name). GDPR allows this: it may be evidence in someone else\'s claim, and deleting it would harm them.',
    ],
    'delete_can_return' => 'You can sign up again with the same Google account any time.',

    'confirm_label' => 'Type DELETE to confirm',
    'confirm_error' => 'Type DELETE exactly to confirm.',
    'delete_button' => 'Delete my account permanently',

    'erased' => 'Your account has been deleted.',

    'stored_title' => 'What we store right now',
    'stored' => [
        'active_listings' => 'Active listings',
        'total_listings'  => 'Listings in total',
        'reveals'         => 'Contacts you have viewed',
        'saved_searches'  => 'Saved searches',
        'favorites'       => 'Favourites',
    ],
];
