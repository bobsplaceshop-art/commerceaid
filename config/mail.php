<?php
declare(strict_types=1);

/**
 * Awalo Global - mail configuration.
 * Real SMTP credentials go in config/local.php.
 * During local development, Laragon's Mailpit catches everything.
 */

return [
    'driver'     => 'smtp',
    'host'       => '127.0.0.1',
    'port'       => 1025,
    'username'   => '',
    'password'   => '',
    'encryption' => '',
    'auth'       => false,
    'timeout'    => 20,
    'from_email' => 'no-reply@anayaglobal.test',
    'from_name'  => 'Awalo Global',
    'reply_to'   => '',
    'debug'      => 0,
];
