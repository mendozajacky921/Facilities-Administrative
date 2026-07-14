<?php
/**
 * modules/dashboard/index.php
 * Milestone 2 will flesh this out fully. For Milestone 0 it already
 * runs real (safe) COUNT queries against the schema so the page isn't
 * just static placeholder markup, and proves db_connect.php works
 * end-to-end.
 *
 * $pdo, e(), page_url() etc. are all available here - the front
 * controller (index.php) already required everything needed.
 *
 * REDESIGN NOTE: all query logic below is unchanged. Only the markup
 * changed - stat cards now show an icon in a colored circle, and
 * quick actions/recent activity are laid out per the reference
 * dashboard (two-column grid on wider screens via .t8-dashboard-grid,
 * dashboard.css).
 */

declare(strict_types=1);

$pageTitle = 'Dashboard';

$stats = [
    'Pending Reservations' => 0,
    'Visitors Today'       => 0,
    'Active Contracts'     => 0,
    'Open Legal Cases'     => 0,
];

// Cosmetic-only metadata per stat card (icon + color variant). Purely
// a display lookup keyed by the same labels above - does not touch
// the $stats values or the queries that populate them.
$statMeta = [
    'Pending Reservations' => ['icon' => 'fa-calendar-check', 'variant' => ''],
    'Visitors Today'       => ['icon' => 'fa-id-card-clip',   'variant' => 't8-stat-icon-info'],
    'Active Contracts'     => ['icon' => 'fa-file-contract',  'variant' => 't8-stat-icon-success'],
    'Open Legal Cases'     => ['icon' => 'fa-scale-balanced', 'variant' => 't8-stat-icon-warning'],
];

// FIX (High, code review): this used to call t8_flash_set() on
// failure, but templates/header.php already reads-and-clears the
// flash stack (t8_flash_get()) before this module file even runs -
// so the warning never appeared on THIS page load, only silently on
// whatever page the user visited next. Flash is a next-request
// pattern; a same-request error belongs in a local variable rendered
// directly below, not a flash.
$dbError = null;

try {
    $stats['Pending Reservations'] = (int) $pdo
        ->query("SELECT COUNT(*) FROM team8_reservations WHERE status = 'pending'")
        ->fetchColumn();

    $stats['Visitors Today'] = (int) $pdo
        ->query("SELECT COUNT(*) FROM team8_visits WHERE DATE(created_at) = CURDATE()")
        ->fetchColumn();

    $stats['Active Contracts'] = (int) $pdo
        ->query("SELECT COUNT(*) FROM team8_contracts WHERE status = 'active'")
        ->fetchColumn();

    $stats['Open Legal Cases'] = (int) $pdo
        ->query("SELECT COUNT(*) FROM team8_legal_cases WHERE status = 'open'")
        ->fetchColumn();
} catch (PDOException $e) {
    // Tables may not be imported yet on a fresh clone - fail soft, not fatal.
    $dbError = 'Could not load live stats - has database/schema.sql been imported yet?';
}
?>
<h1>Welcome, <?= e(t8_current_user_name()) ?></h1>
<p class="t8-help-text">Facilities &amp; Administrative Management overview.</p>

<?php if ($dbError !== null): ?>
    <div class="t8-alert t8-alert-warning"><?= e($dbError) ?></div>
<?php endif; ?>

<div class="t8-stat-grid">
    <?php foreach ($stats as $label => $value): ?>
        <?php $meta = $statMeta[$label] ?? ['icon' => 'fa-chart-simple', 'variant' => '']; ?>
        <div class="t8-stat-card">
            <div class="t8-stat-icon <?= e($meta['variant']) ?>">
                <i class="fa-solid <?= e($meta['icon']) ?>"></i>
            </div>
            <div class="t8-stat-body">
                <div class="t8-stat-value"><?= e((string) $value) ?></div>
                <div class="t8-stat-label"><?= e($label) ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="t8-dashboard-grid">
    <div class="t8-card">
        <div class="t8-card-header">
            <h2 class="t8-card-title">Quick Actions</h2>
        </div>
        <div class="t8-quick-actions">
            <a class="t8-btn t8-btn-accent" href="<?= e(page_url('reservation')) ?>">
                <i class="fa-solid fa-calendar-plus"></i> New Reservation
            </a>
            <a class="t8-btn t8-btn-outline" href="<?= e(page_url('visitor')) ?>">
                <i class="fa-solid fa-id-card-clip"></i> Register Visitor
            </a>
            <a class="t8-btn t8-btn-outline" href="<?= e(page_url('documents')) ?>">
                <i class="fa-solid fa-file-arrow-up"></i> Upload Document
            </a>
            <a class="t8-btn t8-btn-outline" href="<?= e(page_url('contracts')) ?>">
                <i class="fa-solid fa-file-contract"></i> New Contract
            </a>
        </div>
    </div>

    <div class="t8-card">
        <div class="t8-card-header">
            <h2 class="t8-card-title">Recent Activity</h2>
        </div>
        <div class="t8-empty">Activity feed lands in Milestone 2 (reads from the shared <code>audit_logs</code> table).</div>
    </div>
</div>
