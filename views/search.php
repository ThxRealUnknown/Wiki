<?php
/**
 * @var string $query
 * @var array  $grouped  results keyed by archive name
 * @var array  $chapters chapters whose title, text or notes matched
 * @var int    $total
 */
?>
<div class="single-col">
    <div class="page">
        <div class="page-head">
            <h1 class="page-title">
                <?= e($query === '' ? t('Search') : tn($total, '%d result', '%d results')) ?>
            </h1>
            <?php if ($query !== ''): ?>
                <p class="lede"><?= e(t('for “%s” — titles and field contents.', $query)) ?></p>
            <?php else: ?>
                <p class="lede"><?= e(t('Type in the box at the top left to search every archive at once.')) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($query !== '' && $total === 0): ?>
            <div class="empty-state">
                <span class="empty-state-icon">🔍</span>
                <h3><?= e(t('Nothing found')) ?></h3>
                <p><?= e(t('No entry title or field contains “%s”.', $query)) ?></p>
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

        <?php if ($chapters !== []): ?>
            <div class="section">
                <h2 class="section-title">✍ <?= e(t('Draft')) ?> (<?= count($chapters) ?>)</h2>
                <ul class="row-list">
                    <?php foreach ($chapters as $row): ?>
                        <li>
                            <a class="row-item" href="<?= e(url('/draft/' . $row['slug'])) ?>">
                                <div class="row-main">
                                    <div class="row-title"><?= e($row['title']) ?></div>
                                    <div class="row-sub"><?= e(t('Edited')) ?> <?= e(human_time($row['updated_at'])) ?></div>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
