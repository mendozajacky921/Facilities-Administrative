<?php
/**
 * templates/header.php
 * Expects (optionally) $pageTitle to be set by the caller.
 */
declare(strict_types=1);
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>

    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/components.css')) ?>">
    <?php if (($page ?? '') === 'dashboard'): ?>
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
