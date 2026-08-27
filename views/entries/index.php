<?php
/**
 * An archive with nothing selected: the entry list plus a prompt.
 *
 * @var array $category
 * @var array $entries
 * @var array $layouts
 */
?>
<div class="columns">
    <?php include APP_ROOT . '/views/partials/entry_list.php'; ?>

    <div class="content-col">
        <div class="page">
            <div class="page-head">
                <div class="crumbs">
                    <a href="<?= e(url('/')) ?>"><?= e(t('Overview')) ?></a> ›
                    <?= parent_crumb($category) ?><span><?= e($category['name']) ?></span>
                </div>
                <h1 class="page-title">
                    <?= e($category['icon'] ?: '') ?> <?= e($category['name']) ?>
                </h1>
                <?php if (!empty($category['description'])): ?>
                    <p class="lede"><?= e($category['description']) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($entries === []): ?>
                <div class="empty-state">
                    <span class="empty-state-icon"><?= e($category['icon'] ?: '📄') ?></span>
                    <h3><?= e(t('Nothing here yet')) ?></h3>
                    <p>
                        <?= e(t('This archive has %s ready. Create the first entry, or shape the layout first.',
                            tn(count($layouts), '%d layout', '%d layouts'))) ?>
                    </p>
                    <div class="page-actions" style="justify-content:center">
                        <a class="btn btn--primary" href="<?= e(url('/c/' . $category['slug'] . '/new')) ?>">
                            ＋ <?= e(t('New entry')) ?>
                        </a>
                        <a class="btn" href="<?= e(url('/c/' . $category['slug'] . '/layouts')) ?>">
                            <?= e(t('Edit layouts')) ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-state-icon">←</span>
                    <h3><?= e(t('Pick an entry')) ?></h3>
                    <p><?= e(t('Choose something from the list, or add a new one.')) ?></p>
                    <div class="page-actions" style="justify-content:center">
                        <a class="btn btn--primary" href="<?= e(url('/c/' . $category['slug'] . '/new')) ?>">
                            ＋ <?= e(t('New entry')) ?>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
