<?php
/**
 * index.php — Front Controller
 * ---------------------------------------------------------------
 * Every module page is reached through here: index.php?page=reservation
 * Unknown/unlisted pages fall through to a 404 — the route map in
 * app/config/routes.php is a whitelist, not a guess.
 *
 * Flow: bootstrap -> auth check -> resolve route -> render
 *       (header + navbar + sidebar) -> module content -> footer
 * ---------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/constants.php';
require_once __DIR__ . '/app/includes/db_connect.php';
require_once __DIR__ . '/app/includes/auth_check.php';   // sets up $_SESSION, redirects if unauthenticated
require_once __DIR__ . '/app/includes/helpers.php';
require_once __DIR__ . '/app/includes/permissions.php';
require_once __DIR__ . '/app/includes/audit.php';
require_once __DIR__ . '/app/includes/notifications.php';

$routes = require __DIR__ . '/app/config/routes.php';

// FIX (Milestone 3): buffer the whole render so a module can still
// redirect() (POST/redirect/GET) after header.php/navbar.php have
// already been required - see helpers.php's redirect() for the
// matching ob_end_clean(). Flushed once at the very end of this file.
ob_start();

$page = $_GET['page'] ?? 'dashboard';
$t8UnreadNotifications = t8_unread_notification_count($pdo, t8_current_user_id());

// FIX (Medium, code review): routes.php now returns ['file' => ..,
// 'label' => ..] per key instead of a bare file path string.
$moduleFile = array_key_exists($page, $routes)
    ? __DIR__ . '/' . $routes[$page]['file']
    : null;

// FIX (Low, code review): $moduleFile was require'd without checking
// it actually exists, so a routes.php typo (or a moved/renamed module
// file) became an uncaught fatal instead of a graceful error.
if ($moduleFile === null || !is_file($moduleFile)) {
    http_response_code(404);
    $pageTitle = 'Page Not Found';
    require __DIR__ . '/templates/header.php';
    require __DIR__ . '/templates/navbar.php';
    echo '<main class="t8-main t8-main-full">';
    echo '  <div class="t8-alert t8-alert-danger">404 — That page does not exist.</div>';
    echo '  <p><a href="' . e(page_url('dashboard')) . '">Back to dashboard</a></p>';
    echo '</main>';
    require __DIR__ . '/templates/footer.php';
    exit;
}

// $pageTitle can be overridden inside the module file before it echoes
// content — templates/header.php reads it.
$pageTitle = ucfirst($page);

require __DIR__ . '/templates/header.php';
require __DIR__ . '/templates/navbar.php';
?>
<div class="t8-shell">
    <?php require __DIR__ . '/templates/sidebar.php'; ?>
    <main class="t8-main">
        <?php require $moduleFile; ?>
    </main>
</div>
<?php
require __DIR__ . '/templates/footer.php';
ob_end_flush();
