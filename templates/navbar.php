<?php
declare(strict_types=1);
?>
<header class="t8-navbar">
    <div class="t8-navbar-brand">
        <a href="<?= e(page_url('dashboard')) ?>" class="t8-navbar-title"><?= e(APP_NAME) ?></a>
    </div>

    <button class="t8-navbar-toggle" id="t8SidebarToggle" type="button" aria-label="Toggle menu">
        <span></span><span></span><span></span>
    </button>

    <div class="t8-navbar-user">
        <span class="t8-navbar-username"><?= e(t8_current_user_name()) ?></span>
        <span class="t8-badge t8-badge-role"><?= e(t8_current_role() ?? 'guest') ?></span>
        <!--
            FIX (High, code review): logout used to be a bare GET
            <a href>, which is a known anti-pattern for state-changing
            actions (prefetch/crawlers can silently log a user out).
            Now a POST form; logout.php rejects non-POST requests.
        -->
        <form method="post" action="<?= e(APP_URL) ?>/logout.php" class="t8-navbar-logout-form">
            <button type="submit" class="t8-btn t8-btn-ghost t8-btn-sm">Logout</button>
        </form>
    </div>
</header>
