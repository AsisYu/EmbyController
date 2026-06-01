<?php

$enableMailer = true;

if (env('MAIL_TYPE', '') == '' ||
    env('MAIL_HOST', '') == '' ||
    env('MAIL_USER', '') == '' ||
    env('MAIL_PASS', '') == '' ||
    env('MAIL_PORT', '') == '' ||
    env('MAIL_FROM_EMAIL', '') == '' ||
    env('MAIL_FROM_NAME', '') == ''
    ) {
    $enableMailer = false;
}

return [
    'enable'   => $enableMailer,
    'scheme'   => env('MAIL_TYPE', 'smtp'),
    'host'     => env('MAIL_HOST', 'smtp.gmail.com'),
    'username' => env('MAIL_USER', ''),
    'password' => env('MAIL_PASS', 'password'),
    'port'     => (int)env('MAIL_PORT', 587),
    'options'  => [],
    // 'dsn'             => '',
    'embed'    => 'cid:',
    'from'     => [
        'address' => env('MAIL_FROM_EMAIL', ''),
        'name'    => env('MAIL_FROM_NAME', ''),
    ],
    'use_socks5' => env('MAIL_USE_SOCKS5', false),
];
