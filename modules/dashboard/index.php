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
 */

declare(strict_types=1);

$pageTitle = 'Dashboard';

$stats = [
    'Pending Reservations' => 0,
    'Visitors Today'       => 0,
    'Active Contracts'     => 0,
    'Open Legal Cases'     => 0,
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
        <div class="t8-stat-card">
            <div class="t8-stat-value"><?= e((string) $value) ?></div>
            <div class="t8-stat-label"><?= e($label) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="t8-card">
    <div class="t8-card-title">Quick Actions</div>
    <div class="t8-quick-actions">
        <a class="t8-btn t8-btn-accent" href="<?= e(page_url('reservation')) ?>">New Reservation</a>
        <a class="t8-btn t8-btn-outline" href="<?= e(page_url('visitor')) ?>">Register Visitor</a>
        <a class="t8-btn t8-btn-outline" href="<?= e(page_url('documents')) ?>">Upload Document</a>
        <a class="t8-btn t8-btn-outline" href="<?= e(page_url('contracts')) ?>">New Contract</a>
    </div>
</div>

<div class="t8-card">
    <div class="t8-card-title">Recent Activity</div>
    <div class="t8-empty">Activity feed lands in Milestone 2 (reads from the shared <code>audit_logs</code> table).</div>
</div>
