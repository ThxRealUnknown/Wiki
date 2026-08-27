<?php
/**
 * Every layout in one archive.
 *
 * @var array $category
 * @var array $layouts
 */
$layoutsViewActive = true;
$activeEntry = null;
?>
<div class="columns">
    <?php include APP_ROOT . '/views/partials/entry_list.php'; ?>

    <div class="content-col">
        <div class="page">
            <div class="page-head">
                <div class="crumbs">
                    <?= parent_crumb($category) ?><a href="<?= e(url('/c/' . $category['slug'])) ?>"><?= e($category['name']) ?></a> ›
                    <span><?= e(t('Layouts')) ?></span>
                </div>
                <h1 class="page-title"><?= e(t('Layouts')) ?></h1>
                <p class="lede">
                    <?= e(t('A layout is the shape of an entry: which fields it has, in which order. Edit one and every entry built from it changes with it.')) ?>
                </p>
            </div>

            <ul class="row-list">
                <?php foreach ($layouts as $item): ?>
                    <li class="row-item">
                        <div class="row-main">
                            <div class="row-title">
                                <?= e($item['name']) ?>
                                <?php if ((int) $item['is_default'] === 1): ?>
                                    <span class="badge"><?= e(t('Default')) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="row-sub">
                                <?= e(tn((int) $item['entry_count'], '%d entry', '%d entries')) ?>
                                · <?= e(t('updated %s', human_time($item['updated_at']))) ?>
                            </div>
                        </div>
                        <div class="row-actions">
                            <a class="btn btn--sm"
                               href="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $item['id'])) ?>">
                                <?= e(t('Edit fields')) ?>
                            </a>
                            <a class="btn btn--sm btn--ghost"
                               href="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $item['id'] . '/fields')) ?>">
                                <?= e(t('Fields')) ?>
                            </a>
                            <a class="btn btn--sm btn--ghost"
                               href="<?= e(url('/c/' . $category['slug'] . '/new?layout=' . $item['id'])) ?>">
                                <?= e(t('New entry')) ?>
                            </a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($layouts === []): ?>
                <div class="empty-state">
                    <span class="empty-state-icon">▤</span>
                    <h3><?= e(t('No layouts yet')) ?></h3>
                    <p><?= e(t('This archive needs at least one layout before it can hold entries.')) ?></p>
                </div>
            <?php endif; ?>

            <div class="section">
                <h2 class="section-title"><?= e(t('Add a layout')) ?></h2>
                <form method="post" action="<?= e(url('/c/' . $category['slug'] . '/layouts/create')) ?>"
                      class="inline-row" style="max-width:480px">
                    <?= csrf_field() ?>
                    <input class="input" type="text" name="name" required
                           placeholder="<?= e(t('e.g. Short entry, Deity, Timeline event')) ?>">
                    <button class="btn btn--primary" type="submit" style="flex:0 0 auto"><?= e(t('Create')) ?></button>
                </form>
            </div>
        </div>
    </div>
</div>
