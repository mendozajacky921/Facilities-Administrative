<?php
/**
 * login.php  —  TEMPORARY AUTH SYSTEM
 * ---------------------------------------------------------------
 * Stand-in login until the system-wide auth team's real module is
 * integrated. Verifies email/password against the shared `users`
 * table, looks up the user's role from user_roles/roles, and sets
 * the session contract documented in app/includes/auth_check.php.
 *
 * Delete this file (and logout.php) once real auth lands — nothing
 * else in the codebase depends on *how* the session gets populated,
 * only on the session keys themselves.
 * ---------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/includes/db_connect.php';
require_once __DIR__ . '/app/includes/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in? Skip the form.
if (!empty($_SESSION['user_id'])) {
    redirect(APP_URL . '/index.php?page=dashboard');
}

$errors = [];
$emailValue = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailValue = trim($_POST['email'] ?? '');
    $password   = (string) ($_POST['password'] ?? '');

    if (!t8_csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Your session expired. Please try again.';
    } elseif ($emailValue === '' || $password === '') {
        $errors[] = 'Email and password are both required.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, full_name, password_hash, department_id
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $stmt->execute(['email' => $emailValue]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Deliberately vague — never reveal whether the email exists.
            $errors[] = 'Invalid email or password.';
        } else {
            $roleStmt = $pdo->prepare(
                'SELECT r.role_name
                 FROM user_roles ur
                 JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id = :user_id
                 LIMIT 1'
            );
            $roleStmt->execute(['user_id' => $user['id']]);
            $role = $roleStmt->fetchColumn();

            session_regenerate_id(true);
            $_SESSION['user_id']       = (int) $user['id'];
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['role']          = $role ?: 'employee';
            $_SESSION['department_id'] = $user['department_id'] !== null ? (int) $user['department_id'] : null;

            t8_flash_set('success', 'Welcome back, ' . $user['full_name'] . '.');
            redirect(APP_URL . '/index.php?page=dashboard');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
</head>
<body>
<div class="t8-auth-wrapper">
    <div class="t8-card t8-auth-card">
        <div class="t8-auth-badge">Temporary Login</div>
        <h1 class="t8-auth-title"><?= e(APP_NAME) ?></h1>
        <p class="t8-help-text">
            This is a stand-in sign-in built by Team 8. It will be replaced by
            the shared system-wide login once that team's module is integrated.
        </p>

        <?php foreach ($errors as $error): ?>
            <div class="t8-alert t8-alert-danger"><?= e($error) ?></div>
        <?php endforeach; ?>

        <form method="post" action="<?= e(base_url('login.php')) ?>" novalidate>
            <?= t8_csrf_field() ?>

            <div class="t8-field">
                <label class="t8-label" for="email">Email</label>
                <input class="t8-input" type="email" id="email" name="email"
                       value="<?= e($emailValue) ?>" required autofocus>
            </div>

            <div class="t8-field">
                <label class="t8-label" for="password">Password</label>
                <input class="t8-input" type="password" id="password" name="password" required>
            </div>

            <button class="t8-btn t8-btn-accent t8-auth-submit" type="submit">Sign In</button>
        </form>

        <p class="t8-help-text t8-auth-hint">
            Local dev seed account: <code>dev.tester@example.local</code> /
            <code>Password123!</code> (see <code>database/seed.sql</code>).
        </p>
    </div>
</div>
</body>
</html>
