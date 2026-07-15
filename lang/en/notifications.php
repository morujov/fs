<?php

return [
    'greeting' => 'Hi :name!',

    'expiring_soon' => [
        'subject'   => 'Your listing for :number expires soon',
        'line'      => 'Your listing for :number expires in :days days. If it is still available, renew it with one click.',
        'action'    => 'Renew listing',
        'sold_hint' => 'Already sold it? Mark it as sold in your panel and the number will be freed.',
    ],

    'expired' => [
        'subject' => 'Your listing for :number has expired',
        'line'    => 'Your listing for :number has expired and no longer appears on the site. You can reactivate it any time.',
        'action'  => 'Reactivate listing',
    ],
];
