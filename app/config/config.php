<?php
/**
 * config.php
 * Loads .env into getenv()/$_ENV and exposes app-wide constants.
 * No framework/composer dependency — this is a minimal hand-rolled loader.
 *
 * Include this ONCE, early, before anything else (db_connect.php requires it).
 */

declare(strict_types=1);

if (!function_exists('t8_load_env')) {
    function t8_load_env(string $path): void
    {
        if (!is_file($path)) {
            // Fail loudly in dev, quietly in prod (no .env committed on servers
            // that inject real env vars another way).
            if (getenv('APP_ENV') !== 'production') {
                trigger_error("Missing .env file at {$path}. Copy .env.example to .env.", E_USER_WARNING);
            }
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip matching surrounding quotes
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($key === '') {
                continue;
            }

            putenv("{$key}={$value}");
            $_ENV[$key]    = $value;
            $_SERVER[$key] = $value;
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = $_ENV[$key] ?? getenv($key);
        return $value === false ? $default : $value;
    }
}

// Load .env from project root (this file lives in app/config/)
t8_load_env(dirname(__DIR__, 2) . '/.env');

// ---- App-wide constants -------------------------------------------------

define('APP_NAME', env('APP_NAME', 'RAM YUM Facilities & Administrative Management'));
define('APP_ENV', env('APP_ENV', 'local'));
define('APP_DEBUG', filter_var(env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN));
define('APP_URL', rtrim(env('APP_URL', 'http://localhost:8000'), '/'));

define('DB_TEAM8_PREFIX', env('DB_TEAM8_PREFIX', 'team8_'));

define('UPLOAD_MAX_SIZE_MB', (int) env('UPLOAD_MAX_SIZE_MB', 10));
define('UPLOAD_DIR', dirname(__DIR__, 2) . '/' . trim(env('UPLOAD_DIR', 'public/uploads'), '/'));

// Basic error visibility toggle for local dev vs prod
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
