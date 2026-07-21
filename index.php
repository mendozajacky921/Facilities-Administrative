<?php
/**
 * index.php — Front Controller
 * ---------------------------------------------------------------
 * Every module page is reached through here: index.php?page=reservation
 * Unknown/unlisted pages fall through to a 404 — the route map in
 * app/config/routes.php is a whitelist, not a guess.
 *
 * Flow: bootstrap -> auth check -> resolve route -> role check ->
 *       render (header + navbar + sidebar) -> module content -> footer
 *
 * FIX (Facility Management, 2026-07-17): wrapped the whole response in
 * ob_start()/ob_end_flush(). Module files (facilities, reservation)
 * now handle POST-then-redirect themselves via header('Location: ...')
 * the same way login.php/logout.php already do — but unlike those two
 * standalone entry scripts, a module here runs AFTER templates/header.php
 * and navbar.php have already echoed HTML, so a raw header() call would
 * fail with "headers already sent". Output buffering defers everything
 * until the very end of the request, so header()/redirect() still work
 * from inside a module even after earlier templates have "printed".
 * This is the only behavioral change to this file beyond route-level
 * role enforcement below — nothing about how routes resolve changed.
 * ---------------------------------------------------------------
 */

declare(strict_types=1);

ob_start();

require_once __DIR__ . '/app/config/config.php';
require_once __DIR__ . '/app/config/constants.php';
require_once __DIR__ . '/app/includes/db_connect.php';
require_once __DIR__ . '/app/includes/auth_check.php';   // sets up $_SESSION, redirects if unauthenticated
require_once __DIR__ . '/app/includes/helpers.php';
require_once __DIR__ . '/app/includes/permissions.php';
require_once __DIR__ . '/app/includes/audit.php';
require_once __DIR__ . '/app/includes/notifications.php';

$routes = require __DIR__ . '/app/config/routes.php';

$page = $_GET['page'] ?? 'dashboard';
$t8UnreadNotifications = t8_unread_notification_count($pdo, t8_current_user_id());

// FIX (Medium, code review): routes.php now returns ['file' => ..,
// 'label' => .., 'roles' => .. (optional)] per key instead of a bare
// file path string.
$moduleFile = array_key_exists($page, $routes)
    ? __DIR__ . '/' . $routes[$page]['file']
    : null;

// FIX (Low, code review): $moduleFile was require'd without checking
// it actually exists, so a routes.php typo (or a moved/renamed module
// file) became an uncaught fatal error instead of a graceful error.
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
    ob_end_flush();
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
        <?php
        // FIX (Facility Management, 2026-07-17): enforce any
        // route-level 'roles' restriction as a second layer behind
        // sidebar.php hiding the nav link, so a restricted page can't
        // be reached just by typing the URL directly.
        // t8_require_role() is written to close </main></div> and
        // require footer.php itself before exiting (see
        // permissions.php) — it MUST run here, after the shell/main
        // are already open, not earlier, or its closing tags would
        // have nothing matching to close.
        if (!empty($routes[$page]['roles'])) {
            t8_require_role($routes[$page]['roles']);
        }
        require $moduleFile;
        ?>
    </main>
</div>
<?php
require __DIR__ . '/templates/footer.php';
ob_end_flush();
