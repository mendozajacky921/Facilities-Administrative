<?php
/**
 * logout.php  —  TEMPORARY AUTH SYSTEM
 * Destroys the session created by login.php. Delete alongside
 * login.php once real auth is integrated.
 */

declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
