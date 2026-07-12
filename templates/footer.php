<?php declare(strict_types=1); ?>
<footer class="t8-footer">
    <p>&copy; <?= date('Y') ?> <?= e(APP_NAME) ?> — Team 8 (RAM YUM)</p>
</footer>

<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php if (($page ?? '') === 'reservation' || ($page ?? '') === 'contracts'): ?>
    <script src="<?= e(asset('js/validation.js')) ?>"></script>
<?php endif; ?>
</body>
</html>
