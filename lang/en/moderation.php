<?php

return [
    'reasons' => [
        'msisdn_malformed'      => 'The number must have 9 digits.',
        'msisdn_not_sellable'   => ':reason',
        'blocklisted'           => 'This number cannot be published. If you think this is a mistake, contact us.',
        'duplicate'             => 'This number already has an active listing. Only one is allowed.',
        'price_missing'         => 'Set a price or mark the listing as negotiable.',
        'price_out_of_range'    => 'The price must be between €:min and €:max.',

        'contact_phone_missing'   => 'Your contact phone is missing.',
        'contact_phone_malformed' => 'Your contact phone does not look valid.',
        'contact_equals_msisdn'   => 'Your contact phone is the same number you are selling. If you sell it, buyers will not reach you. Use a different one.',
        'contact_email_malformed' => 'Your email does not look valid.',
        'contact_email_disposable' => 'That email looks temporary. Use one you can still read in a few weeks.',

        'otp_pending' => 'Verify your number with the code we sent you by SMS.',

        'contacts_in_text'   => 'It looks like you put contact details in the description. A reviewer will check it.',
        'forbidden_content'  => 'The description needs a manual review.',
        'rate_limit'         => 'You have posted several listings in a short time (:max per day). A reviewer will check it.',
        'account_too_new'    => 'Your account is very new. Your first listing goes through review.',
        'shop_unverified'    => 'Your shop is pending verification. We will publish the listing once we validate it.',
        'shop_missing'       => 'You selected «shop» but have not registered one. Register it to publish.',

        'manual_review'      => 'Your listing is under review. It usually does not take long.',
    ],

    'rules' => [
        'number_sellable'   => 'Numbering range',
        'blocklist'         => 'Blocklist',
        'duplicate'         => 'Active duplicate',
        'price_range'       => 'Price range',
        'contacts_valid'    => 'Valid contacts',
        'otp_verified'      => 'Number verified (OTP)',
        'contacts_in_text'  => 'Contacts in description',
        'forbidden_content' => 'Forbidden content',
        'rate_limit'        => 'Posting rate',
        'account_age'       => 'Account age',
        'shop_verified'     => 'Verified shop',
    ],

    'outcomes' => [
        'pass'   => 'No issues',
        'flag'   => 'Suspicious',
        'reject' => 'Rejected',
        'hold'   => 'Waiting',
    ],
];
