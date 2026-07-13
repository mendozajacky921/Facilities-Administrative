<?php
/**
 * templates/header.php
 *
 * FIX (Medium, code review): this template reaches into the including
 * scope's local variables ($pageTitle, $page) implicitly rather than
 * receiving them as parameters - that's fine for `require` (shares
 * scope), but it's an implicit contract that breaks silently (e.g.
 * the dashboard.css link below just stops loading, no error/warning)
 * if index.php ever renames $page. Documented explicitly here, and
 * given a safe fallback via current_page() so a missing $page doesn't
 * silently disable the page-specific stylesheet.
 *
 * Expects (optionally) from the including scope:
 *   $pageTitle - string, defaults to APP_NAME
 *   $page      - route key string, defaults to current_page()
 */
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;
$page      = $page ?? current_page();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>

    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <?php if ($page === 'dashboard'): ?>
        <link rel="stylesheet" href="<?= e(asset('css/dashboard.css')) ?>">
    <?php endif; ?>
</head>
<body>
<?php $flashes = t8_flash_get(); ?>
<?php if (!empty($flashes)): ?>
    <div class="t8-flash-stack">
        <?php foreach ($flashes as $flash): ?>
            <div class="t8-alert t8-alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
