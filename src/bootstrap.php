<?php

/**
 * Single entry point for everything the app needs before handling a request.
 * Included by public/index.php and by the scripts in bin/.
 */

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, 4));
    $path = APP_ROOT . '/src/' . $relative . '.php';

    if (is_file($path)) {
        require $path;
    }
});

require APP_ROOT . '/src/helpers.php';

mb_internal_encoding('UTF-8');
date_default_timezone_set(date_default_timezone_get() ?: 'UTC');

if (App\Config::get('debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}
