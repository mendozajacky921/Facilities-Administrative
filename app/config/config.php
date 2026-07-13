<?php
/**
 * config.php
 * App-wide configuration and constants.
 *
 * CHANGED (per request): this project no longer uses a .env file.
 * All app-level settings are plain PHP constants below - edit the
 * values directly for your local setup. DB credentials live in
 * app/config/database.php (same approach, kept separate so the two
 * concerns don't mix).
 *
 * This is a reasonable simplification for this project's scope: the
 * whole app/ tree (including this file) is already blocked from
 * direct HTTP access by the root .htaccess + app/.htaccess deny-all,
 * the same protection a .env file relied on. If this codebase ever
 * needs different settings per deploy environment (e.g. real staging
 * vs. production with different DB creds), that's the point to
 * reintroduce environment variables - not before.
 *
 * Include this ONCE, early, before anything else (db_connect.php requires it).
 */

declare(strict_types=1);

// ---- App-wide settings — edit these for your environment ---------------

define('APP_NAME', 'RAM YUM - Facilities & Administrative Management');
define('APP_ENV', 'local');              // 'local' | 'production'
define('APP_DEBUG', true);               // true = show real PHP errors (local only)
define('APP_URL', 'http://localhost:8000');
define('APP_TIMEZONE', 'Asia/Manila');

define('DB_TEAM8_PREFIX', 'team8_');

define('UPLOAD_MAX_SIZE_MB', 10);
define('UPLOAD_DIR', dirname(__DIR__, 2) . '/public/uploads');

// TEMPORARY AUTH dev bypass (see login.php / logout.php / docs/Auth.md).
// When true, skips the login form entirely and auto-logs in as the
// seeded "Dev Tester" (user_id 1). Leave false unless doing quick
// throwaway local testing — auth_check.php also requires
// APP_ENV === 'local' regardless of this flag, so it can never
// accidentally apply outside local dev.
define('AUTH_DEV_BYPASS', false);

// ---- End editable section ------------------------------------------------

date_default_timezone_set(APP_TIMEZONE);

// Basic error visibility toggle for local dev vs prod
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
}
