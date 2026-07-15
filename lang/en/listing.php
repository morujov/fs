<?php

return [
    'create_title' => 'Sell a number',

    'legal_notice' => 'Line transfer listing. The transfer happens via portability between the parties and their operators. This site is not a party to the transaction.',

    'submit'      => 'Publish listing',
    'my_listings' => 'My listings',
    'no_listings' => 'You have not published any listing yet.',

    'submitted_without_otp' => 'Listing created. It will go to review.',

    'attributes' => [
        'msisdn'           => 'number',
        'price'            => 'price',
        'is_negotiable'    => 'negotiable price',
        'operator_id'      => 'operator',
        'line_type'        => 'line type',
        'has_permanency'   => 'contract commitment',
        'permanency_until' => 'commitment end date',
        'condition'        => 'condition',
        'province_id'      => 'province',
        'city'             => 'city',
        'description'      => 'comments',
        'contact_name'     => 'your name',
        'contact_phone'    => 'your contact phone',
        'contact_email'    => 'your email',
        'contact_whatsapp' => 'WhatsApp',
        'seller_type'      => 'seller type',
    ],

    'messages' => [
        'price.required' => 'Set a price or mark the listing as negotiable.',
        'permanency_until.required' => 'Tell us when the commitment ends.',
        'permanency_until.after' => 'The commitment date must be in the future.',
    ],

    'validation' => [
        'msisdn_malformed'      => 'The number must have 9 digits.',
        'msisdn_not_sellable'   => 'This number cannot be sold on this platform.',
        'msisdn_already_listed' => 'This number already has an active listing.',
    ],

    'help' => [
        'msisdn'        => 'The number you are selling. 9 digits, as you would dial it.',
        'condition_new' => 'New: never activated.',
        'condition_used'=> 'Used: has been in use.',
        'contact_phone' => 'Your phone so buyers can reach you. Not the number you are selling. It stays hidden until the buyer signs in with Google.',
        'permanency'    => 'Whether the line has a contract commitment with the operator.',
        'negotiable'    => 'Tick this if you prefer to negotiate the price.',
    ],

    'conditions' => [
        'new'  => 'New',
        'used' => 'Used',
    ],

    'line_types' => [
        'prepago'  => 'Prepaid',
        'contrato' => 'Contract',
    ],

    'seller_types' => [
        'private' => 'Private',
        'shop'    => 'Shop',
    ],

    'statuses' => [
        'draft'    => 'Draft',
        'pending'  => 'In review',
        'active'   => 'Published',
        'rejected' => 'Rejected',
        'sold'     => 'Sold',
        'expired'  => 'Expired',
        'archived' => 'Archived',
    ],
];
