<?php
/**
 * TerraWatch-1 — application bootstrap (module M1 Foundation).
 * AI-assisted code: written with Claude Code (Anthropic) and reviewed by the team.
 *
 * Loads configuration, configures error handling, starts a hardened session
 * and exposes the shared helpers. Every public entry point requires this file.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

// TW_CONFIG lets a host (or a test) point at a config file kept outside the project.
$configFile = getenv('TW_CONFIG') ?: APP_ROOT . '/config/config.local.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration missing: copy config/config.example.php to config/config.local.php.');
}
$GLOBALS['APP_CONFIG'] = require $configFile;

function config(string $key, $default = null)
{
    return $GLOBALS['APP_CONFIG'][$key] ?? $default;
}

// Errors: shown only in development, always logged.
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');
ini_set('display_errors', config('env') === 'development' ? '1' : '0');

set_exception_handler(function (Throwable $e): void {
    error_log('[' . date('c') . '] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (config('env') === 'development') {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'Something went wrong. Please go back and try again.';
    }
});

// Security headers: no framing, no MIME sniffing, scripts only from this site.
// (Inline style attributes are used for meter widths, so style-src allows 'unsafe-inline'.)
if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; "
        . "connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}

// Session: no accounts; the mission belongs to this browser session.
if (session_status() !== PHP_SESSION_ACTIVE && PHP_SAPI !== 'cli') {
    session_name('tw1sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

require APP_ROOT . '/src/db.php';
require APP_ROOT . '/src/helpers.php';
