<?php
/**
 * @var int    $status
 * @var string $message
 */
?>
<div class="single-col">
    <div class="page">
        <div class="empty-state">
            <span class="empty-state-icon"><?= $status === 404 ? '🧭' : '⚠' ?></span>
            <h3><?= (int) $status ?> — <?= e($pageTitle ?? t('Error')) ?></h3>
            <p><?= e($message) ?></p>
            <a class="btn" href="<?= e(url('/')) ?>"><?= e(t('Back to the overview')) ?></a>
        </div>
    </div>
</div>
