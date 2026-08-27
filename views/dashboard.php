<?php
/**
 * @var array $categories
 * @var array $recent
 */
$total = 0;
foreach ($categories as $item) {
    $total += (int) $item['entry_count'];
}
?>
<div class="single-col">
    <div class="page page--wide">
        <div class="page-head">
            <h1 class="page-title"><?= e(t('Your world')) ?></h1>
            <p class="lede">
                <?= e(tn(count($categories), '%d archive', '%d archives')) ?>,
                <?= e(tn($total, '%d entry', '%d entries')) ?>.
            </p>
        </div>

        <?php if ($categories === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon">📖</span>
                <h3><?= e(t('Start with an archive')) ?></h3>
                <p>
                    <?= e(t('An archive is a category of things — characters, species, magic systems, cities. Each one gets its own layouts.')) ?>
                </p>
                <a class="btn btn--primary" href="<?= e(url('/archives')) ?>"><?= e(t('Create the first archive')) ?></a>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($tree as $item): ?>
                    <div class="card-stack">
                        <a class="card" href="<?= e(url('/c/' . $item['slug'])) ?>"
                           style="--archive-color: <?= e($item['color'] ?: '#8a8f98') ?>">
                            <span class="card-icon"><?= e($item['icon'] ?: '•') ?></span>
                            <div class="card-title"><?= e($item['name']) ?></div>
                            <div class="card-sub">
                                <?= e(tn((int) $item['entry_count'], '%d entry', '%d entries')) ?>
                            </div>
                            <?php if (!empty($item['description'])): ?>
                                <div class="card-desc"><?= e($item['description']) ?></div>
                            <?php endif; ?>
                        </a>

                        <?php if ($item['children'] !== []): ?>
                            <div class="card-children">
                                <?php foreach ($item['children'] as $child): ?>
                                    <a class="chip chip--link" href="<?= e(url('/c/' . $child['slug'])) ?>">
                                        <span class="chip-icon"><?= e($child['icon'] ?: '•') ?></span>
                                        <?= e($child['name']) ?>
                                        <span class="archive-count"><?= (int) $child['entry_count'] ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($recent !== []): ?>
            <div class="section">
                <h2 class="section-title"><?= e(t('Recently edited')) ?></h2>
                <ul class="row-list">
                    <?php foreach ($recent as $item): ?>
                        <li>
                            <a class="row-item"
                               href="<?= e(url('/c/' . $item['category_slug'] . '/e/' . $item['slug'])) ?>">
                                <span class="chip-icon" style="font-size:15px">
                                    <?= e($item['category_icon'] ?: '•') ?>
                                </span>
                                <div class="row-main">
                                    <div class="row-title"><?= e($item['title']) ?></div>
                                    <div class="row-sub">
                                        <?= e($item['category_name']) ?> ·
                                        <?= e(human_time($item['updated_at'])) ?>
                                    </div>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>
