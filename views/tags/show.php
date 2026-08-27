<?php
/**
 * Every entry carrying one tag, grouped by archive.
 *
 * @var string $tag
 * @var array  $grouped results keyed by archive name
 * @var int    $total
 */
?>
<div class="single-col">
    <div class="page">
        <div class="page-head">
            <div class="crumbs">
                <a href="<?= e(url('/settings')) ?>"><?= e(t('Settings')) ?></a> ›
                <span><?= e(t('Tag')) ?></span>
            </div>
            <h1 class="page-title">🏷 <?= e($tag) ?></h1>
            <p class="lede"><?= e(tn($total, '%d entry.', '%d entries.')) ?></p>
        </div>

        <?php if ($total === 0): ?>
            <div class="empty-state">
                <span class="empty-state-icon">🏷</span>
                <h3><?= e(t('Nothing carries this tag')) ?></h3>
                <p><?= e(t('No entry currently has “%s” in a tags field.', $tag)) ?></p>
            </div>
        <?php endif; ?>

        <?php foreach ($grouped as $categoryName => $rows): ?>
            <div class="section">
                <h2 class="section-title">
                    <?= e($rows[0]['category_icon'] ?: '•') ?> <?= e($categoryName) ?>
                    (<?= count($rows) ?>)
                </h2>
                <ul class="row-list">
                    <?php foreach ($rows as $row): ?>
                        <li>
                            <a class="row-item"
                               href="<?= e(url('/c/' . $row['category_slug'] . '/e/' . $row['slug'])) ?>">
                                <div class="row-main">
                                    <div class="row-title"><?= e($row['title']) ?></div>
                                    <div class="row-sub"><?= e(t('Edited')) ?> <?= e(human_time($row['updated_at'])) ?></div>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
</div>
