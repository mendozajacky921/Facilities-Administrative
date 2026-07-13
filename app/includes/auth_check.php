<?php
/**
 * auth_check.php  —  TEMPORARY AUTH SYSTEM
 * ---------------------------------------------------------------
 * Login/logout/session ownership belongs to another team's
 * system-wide auth module, which hasn't landed yet. Until it does,
 * Team 8 built a real (if minimal) stand-in — see login.php and
 * logout.php at the project root — so the app isn't blocked and
 * every module can be built/demoed with an actual login flow instead
 * of a fake session.
 *
 * Session contract (this is what the real auth team is expected to
 * provide eventually — keep any future swap limited to deleting
 * login.php/logout.php's user-facing bits, not this contract):
 *
 *      $_SESSION['user_id']
 *      $_SESSION['full_name']
 *      $_SESSION['role']          (string, e.g. 'admin')
 *      $_SESSION['department_id']
 *
 * AUTH_DEV_BYPASS=true (app/config/config.php) skips login.php
 * entirely and auto-logs in as the seeded "Dev Tester" — opt-in only,
 * off by default, and only ever active when APP_ENV=local regardless
 * of the flag.
 * ---------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

// FIX (Medium, code review): was a raw session_start() with no cookie
// hardening; now goes through the shared, hardened t8_session_start()
// (httponly/samesite/secure cookie params) so all three session
// entry points (here, login.php, logout.php) stay in sync.
t8_session_start();

// ---- Optional dev bypass (local env only, off by default) --------------
// CHANGED (per request): AUTH_DEV_BYPASS is now a plain constant from
// config.php (no more .env/env()).
$devBypass = APP_ENV === 'local' && AUTH_DEV_BYPASS === true;
if ($devBypass && empty($_SESSION['user_id'])) {
    $_SESSION['user_id']       = 1;
    $_SESSION['full_name']     = 'Dev Tester';
    $_SESSION['role']          = 'admin';
    $_SESSION['department_id'] = null;
}
// ---- END dev bypass -------------------------------------------------------

// Real guard — sends anyone without a session to the temporary login form.
if (empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

/**
 * Convenience accessors — modules should use these instead of touching
 * $_SESSION directly, so swapping in real auth later only changes
 * login.php/logout.php, not every module.
 */
function t8_current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function t8_current_user_name(): string
{
    return $_SESSION['full_name'] ?? 'Unknown User';
}

function t8_current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}
