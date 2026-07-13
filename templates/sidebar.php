<?php
declare(strict_types=1);

// FIX (Medium, code review): this used to keep its own hand-written
// $navItems list, duplicating routes.php and constants.php's T8_PAGES.
// Labels now come straight from routes.php (the single source of
// truth - see app/config/routes.php).
$t8Routes = require __DIR__ . '/../app/config/routes.php';
$active   = current_page();
?>
<aside class="t8-sidebar" id="t8Sidebar">
    <nav class="t8-sidebar-nav">
        <?php foreach ($t8Routes as $key => $route): ?>
            <a href="<?= e(page_url($key)) ?>"
               class="t8-sidebar-link<?= $active === $key ? ' t8-sidebar-link-active' : '' ?>">
                <span class="t8-sidebar-label"><?= e($route['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
