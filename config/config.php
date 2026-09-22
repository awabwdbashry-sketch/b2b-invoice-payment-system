<?php
/**
 * Application configuration loader.
 * Reads key=value pairs from a local .env file (never committed with real
 * secrets) and falls back to safe defaults. Also defines app-wide constants.
 */

declare(strict_types=1);

// ---------------------------------------------------------------
// Load .env (simple parser, no external dependency required)
// ---------------------------------------------------------------
$envPath = __DIR__ . '/.env';
$env = [];
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value, " \t\n\r\0\x0B\"'");
    }
}

/** Read a config value: .env > real environment variable > default */
function env(string $key, $default = null)
{
    global $env;
    if (array_key_exists($key, $env)) {
        return $env[$key];
    }
    $v = getenv($key);
    return $v !== false ? $v : $default;
}

// ---------------------------------------------------------------
// Environment
// ---------------------------------------------------------------
define('APP_ENV', env('APP_ENV', 'production'));      // 'local' | 'production'
define('APP_DEBUG', APP_ENV === 'local');
define('APP_NAME', env('APP_NAME', 'B2B Invoice & Payment Tracking System'));
define('APP_NAME_AR', 'نظام فوترة ومتابعة المدفوعات B2B');
define('APP_LOCALE', 'ar');
define('APP_URL', env('APP_URL', 'http://localhost'));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Africa/Cairo'));

date_default_timezone_set(APP_TIMEZONE);

// ---------------------------------------------------------------
// Database
// ---------------------------------------------------------------
define('DB_DRIVER', env('DB_DRIVER', 'mysql'));   // 'mysql' | 'sqlite' (sqlite used only for local dev/testing)
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'b2b_invoice'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_SQLITE_PATH', env('DB_SQLITE_PATH', __DIR__ . '/../database/dev.sqlite'));

// ---------------------------------------------------------------
// Security
// ---------------------------------------------------------------
define('SESSION_NAME', 'b2b_invoice_sess');
define('SESSION_LIFETIME', 60 * 60 * 4); // 4 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// ---------------------------------------------------------------
// Mail / SMTP (used to email invoices to customers)
// ---------------------------------------------------------------
define('MAIL_HOST', env('MAIL_HOST', ''));
define('MAIL_PORT', (int)env('MAIL_PORT', 587));
define('MAIL_USERNAME', env('MAIL_USERNAME', ''));
define('MAIL_PASSWORD', env('MAIL_PASSWORD', ''));
define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'tls')); // 'tls' | 'ssl' | ''
define('MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'no-reply@example.com'));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', APP_NAME));
define('MAIL_CONFIGURED', MAIL_HOST !== '' && MAIL_USERNAME !== '');

// ---------------------------------------------------------------
// Error handling: never leak internals in production
// ---------------------------------------------------------------
ini_set('display_errors', APP_DEBUG ? '1' : '0');
ini_set('display_startup_errors', APP_DEBUG ? '1' : '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../storage_php_errors.log');
