<?php
declare(strict_types=1);

$navItems = [
    'dashboard'   => 'Dashboard',
    'reservation' => 'Facilities Reservation',
    'visitor'     => 'Visitor Management',
    'documents'   => 'Document Management',
    'retention'   => 'Records Retention',
    'legal'       => 'Legal Management',
    'contracts'   => 'Contract Management',
];

$active = current_page();
?>
<aside class="t8-sidebar" id="t8Sidebar">
    <nav class="t8-sidebar-nav">
        <?php foreach ($navItems as $key => $label): ?>
            <a href="<?= e(page_url($key)) ?>"
               class="t8-sidebar-link<?= $active === $key ? ' t8-sidebar-link-active' : '' ?>">
                <span class="t8-sidebar-label"><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
