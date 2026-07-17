<?php
/**
 * logout.php  —  TEMPORARY AUTH SYSTEM
 * Destroys the session created by login.php. Delete alongside
 * login.php once real auth is integrated.
 *
 * FIX (High, code review): logout used to be a bare GET link
 * (templates/navbar.php), so a stray <img src>, link prefetch, or
 * crawler could silently log a user out. templates/navbar.php now
 * submits this as a POST form; this script rejects anything that
 * isn't POST.
 */

declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/includes/helpers.php';

t8_session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // FIX (Milestone 3 review): header('Location: ...') silently
    // overrides an already-set http_response_code() with its own
    // default 302 - so this used to claim 405 but actually sent a
    // redirect (confirmed with `curl -i`). A non-POST request here is
    // not a normal navigation to recover from anyway (the only caller,
    // templates/navbar.php, always POSTs) - send a real 405 with a
    // small body instead of redirecting.
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "405 Method Not Allowed - logout must be a POST request.";
    exit;
}

// Best-effort audit entry before the session (and $_SESSION['user_id'])
// is wiped below.
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/app/includes/db_connect.php';
    require_once __DIR__ . '/app/includes/audit.php';
    t8_audit_log($pdo, (int) $_SESSION['user_id'], 'user', (int) $_SESSION['user_id'], 'logout');
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: ' . APP_URL . '/login.php');
exit;
