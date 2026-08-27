<?php

/**
 * Router for PHP's built-in web server (used by serve.bat), doing what
 * .htaccess does for Apache: serve real files, hand everything else to
 * index.php. Never loaded by Apache.
 */

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$file = __DIR__ . '/' . ltrim(rawurldecode((string) $path), '/');

// A real file underneath public/ is served untouched — but never a PHP script,
// which would otherwise let anything dropped into uploads/ be executed.
if ($path !== '/' && is_file($file) && !preg_match('/\.(php|phtml|phar)$/i', $file)) {
    return false;
}

require __DIR__ . '/index.php';
