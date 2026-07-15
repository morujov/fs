<?php

return [
    'title'  => 'Report listing',
    'intro'  => 'Tell us what is wrong with this listing. No account needed.',
    'submit' => 'Send report',
    'thanks' => 'Thank you. A reviewer will check it.',

    'attributes' => [
        'reason'         => 'reason',
        'comment'        => 'comment',
        'reporter_email' => 'your email',
    ],

    'reasons' => [
        'not_mine'   => 'This is my number and I am not selling it',
        'fraud'      => 'I think this is a scam',
        'wrong_info' => 'The details are wrong',
        'spam'       => 'This is spam or advertising',
        'sold'       => 'It is already sold',
        'other'      => 'Other reason',
    ],

    'comment_help' => 'Optional, but it helps us understand the case.',
    'email_help'   => 'Optional. Only if you want us to tell you what we did.',
];
