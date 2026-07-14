<?php
/**
 * templates/navbar.php
 *
 * REDESIGN: white sticky top bar with search/notification/avatar per
 * the reference dashboard. #t8SidebarToggle keeps its id (public/js/app.js
 * binds a click listener to it for the mobile sidebar) - only its
 * visual style changed. Logout is still the same POST <form> to
 * logout.php (see docs/Auth.md / TechDebt.md - do not revert to a
 * GET link).
 *
 * The search bar and notification bell are DECORATIVE ONLY - there is
 * no backend search or notification system in this codebase yet, so
 * wiring them up is out of scope here. They're inert markup so the
 * page matches the reference visually without inventing fake behavior.
 *
 * Expects (optionally) from the including scope:
 *   $pageTitle - string, defaults to APP_NAME (same contract as header.php)
 */
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;

// Small local lookup for the subtitle line under the page title.
// Not part of routes.php's contract (file/label) - purely cosmetic,
// safe to extend per-page without touching the route whitelist.
$t8NavSubtitles = [
    'dashboard'   => 'Overview of your store operations',
    'reservation' => 'Manage facility bookings and approvals',
    'visitor'     => 'Track visitor check-in and check-out',
    'documents'   => 'Upload, version, and archive documents',
    'retention'   => 'Retention schedules and compliance checks',
    'legal'       => 'Legal cases and related filings',
    'contracts'   => 'Contracts, parties, and obligations',
];
$t8NavSubtitle = $t8NavSubtitles[current_page()] ?? '';

$t8UserName = function_exists('t8_current_user_name') ? t8_current_user_name() : 'Guest';
$t8UserInitial = strtoupper(substr(trim($t8UserName), 0, 1) ?: '?');
?>
<header class="t8-navbar">
    <div class="t8-navbar-left">
        <button class="t8-navbar-toggle" id="t8SidebarToggle" type="button" aria-label="Toggle menu">
            <span></span><span></span><span></span>
        </button>
        <div class="t8-navbar-heading">
            <div class="t8-navbar-title"><?= e($pageTitle) ?></div>
            <?php if ($t8NavSubtitle !== ''): ?>
                <div class="t8-navbar-subtitle"><?= e($t8NavSubtitle) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="t8-navbar-search" aria-hidden="true">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" placeholder="Search anything..." disabled>
    </div>

    <div class="t8-navbar-user">
        <span class="t8-navbar-bell" aria-hidden="true">
            <i class="fa-regular fa-bell"></i>
            <span class="t8-navbar-bell-dot"></span>
        </span>

        <span class="t8-navbar-avatar"><?= e($t8UserInitial) ?></span>
        <span class="t8-navbar-username-block">
            <span class="t8-navbar-username"><?= e($t8UserName) ?></span>
            <span class="t8-navbar-role-text"><?= e(t8_current_role() ?? 'guest') ?></span>
        </span>

        <!--
            FIX (High, code review): logout used to be a bare GET
            <a href>, which is a known anti-pattern for state-changing
            actions (prefetch/crawlers can silently log a user out).
            Now a POST form; logout.php rejects non-POST requests.
        -->
        <form method="post" action="<?= e(APP_URL) ?>/logout.php" class="t8-navbar-logout-form">
            <button type="submit" class="t8-btn t8-btn-outline t8-btn-sm">
                <i class="fa-solid fa-arrow-right-from-bracket"></i>
                <span>Logout</span>
            </button>
        </form>
    </div>
</header>
