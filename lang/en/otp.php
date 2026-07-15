<?php

return [
    'sms_text' => 'Your Números ES verification code is :code. It expires in :minutes minutes.',

    'title'    => 'Verify your number',
    'intro'    => 'We sent a 6-digit code by SMS to :number. Enter it to publish your listing.',
    'why'      => 'We verify that the number is yours. This prevents anyone from listing someone else\'s number.',

    'code'     => 'Code',
    'verify'   => 'Verify',
    'resend'   => 'Resend code',

    'verified' => 'Number verified. Your listing will go to review.',
    'resent'   => 'We sent you a new code.',

    'attributes' => [
        'code' => 'code',
    ],

    'errors' => [
        'not_found'         => 'No pending code. Request a new one.',
        'expired'           => 'The code has expired. Request a new one.',
        'invalid'           => 'Wrong code. You have :left attempts left.',
        'too_many_attempts' => 'You have used all :max attempts. Request a new code.',
        'too_many_sends'    => 'You have reached the limit of :max sends for this number.',
    ],
];
