<?php
declare(strict_types=1);

/**
 * Anaya Global - commerce platform front controller.
 * Every HTTP request enters here. Only /public is web-accessible.
 */

define('CA_APP_START', microtime(true));
define('CA_BASE_PATH', dirname(__DIR__));
define('CA_PUBLIC_PATH', __DIR__);

// A short, unique-per-request identifier. Every log line
// written while serving this request carries it, and every
// error response carries it as the X-Request-ID header. A
// user quoting the reference from the error page, plus the
// request ID visible in the browser Network tab, is
// enough to locate the exact log entry.
//
// 12 hex characters = 48 bits of entropy. Collisions are
// negligible at any scale this platform will reach. Short
// enough to read aloud over the phone if needed.
define('CA_REQUEST_ID', bin2hex(random_bytes(6)));

// ------------------------------------------------------------------
// PHP environment checks
// ------------------------------------------------------------------
if (PHP_SAPI === 'cli') {
    fwrite(STDERR, "Front controller is web-only.\n");
    exit(1);
}

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('This application requires PHP 8.2 or newer. Current: ' . PHP_VERSION);
}

$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'fileinfo', 'ctype', 'tokenizer', 'dom', 'gd'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Cannot start: missing PHP extension '{$ext}'.");
    }
}

// ------------------------------------------------------------------
// Composer autoloader + local config
// ------------------------------------------------------------------
$autoload = CA_BASE_PATH . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Cannot start: vendor/autoload.php is missing. Run `composer install`.');
}
require $autoload;

$localConfig = CA_BASE_PATH . '/config/local.php';
if (!is_file($localConfig)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Cannot start: config/local.php is missing. Copy config/local.php.example and fill in your values.');
}

// ------------------------------------------------------------------
// Boot and run
// ------------------------------------------------------------------
\CorporateAid\Core\App::boot(CA_BASE_PATH)->run();
