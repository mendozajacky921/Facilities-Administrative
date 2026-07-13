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
    http_response_code(405);
    header('Location: ' . APP_URL . '/index.php?page=dashboard');
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
