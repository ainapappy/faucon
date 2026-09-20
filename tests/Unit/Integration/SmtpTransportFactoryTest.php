<?php

use App\Services\Integration\SmtpTransportFactory;

test('dsnFor builds a complete dsn with encoded userinfo', function () {
    $dsn = (new SmtpTransportFactory)->dsnFor([
        'host' => 'smtp.exemple.com',
        'port' => 587,
        'encryption' => 'tls',
        'username' => 'user@exemple.com',
        'password' => 'p:ass/word',
    ]);

    expect($dsn)->toBe('smtp://user%40exemple.com:p%3Aass%2Fword@smtp.exemple.com:587?encryption=tls');
});

test('dsnFor uses the ssl query flag for the ssl encryption', function () {
    $dsn = (new SmtpTransportFactory)->dsnFor([
        'host' => 'smtp.exemple.com',
        'port' => 465,
        'encryption' => 'ssl',
    ]);

    expect($dsn)->toBe('smtp://smtp.exemple.com:465?encryption=ssl');
});

test('dsnFor omits the encryption flag when none is requested', function () {
    $dsn = (new SmtpTransportFactory)->dsnFor([
        'host' => 'smtp.exemple.com',
        'port' => 25,
        'encryption' => 'none',
    ]);

    expect($dsn)->toBe('smtp://smtp.exemple.com:25');
});

test('dsnFor defaults the encryption to tls', function () {
    $dsn = (new SmtpTransportFactory)->dsnFor([
        'host' => 'smtp.exemple.com',
        'port' => 587,
    ]);

    expect($dsn)->toBe('smtp://smtp.exemple.com:587?encryption=tls');
});

test('dsnFor omits the port when absent', function () {
    $dsn = (new SmtpTransportFactory)->dsnFor([
        'host' => 'smtp.exemple.com',
    ]);

    expect($dsn)->toBe('smtp://smtp.exemple.com?encryption=tls');
});

test('dsnFor omits the userinfo when the username is absent', function () {
    $dsn = (new SmtpTransportFactory)->dsnFor([
        'host' => 'smtp.exemple.com',
        'port' => 587,
        'encryption' => 'none',
        'password' => 'orphan-password',
    ]);

    expect($dsn)->toBe('smtp://smtp.exemple.com:587');
});
