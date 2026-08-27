<?php

/**
 * Entries taken out of circulation but not deleted.
 *
 * @var array $category
 * @var array $archived  archived entries in this category
 */
$archivedViewActive = true;
?>
<div class="columns">
    <?php include APP_ROOT . '/views/partials/entry_list.php'; ?>

    <div class="content-col">
        <div class="page">
            <div class="page-head">
                <div class="crumbs">
                    <?= parent_crumb($category) ?><a href="<?= e(url('/c/' . $category['slug'])) ?>"><?= e($category['name']) ?></a> ›
                    <span><?= e(t('Archived')) ?></span>
                </div>
                <h1 class="page-title"><?= e(t('Archived')) ?></h1>
                <p class="lede">
                    <?= e(t('Archiving an entry hides it everywhere else, but keeps everything it holds. Put it back at any time.')) ?>
                </p>
            </div>

            <div class="section" style="margin-top:0">
                <h2 class="section-title"><?= e(t('Archived — %d', count($archived))) ?></h2>

                <?php if ($archived === []): ?>
                    <p class="field-help">
                        <?= e(t('Nothing archived. Archive an entry from its own page and it will appear here.')) ?>
                    </p>
                <?php else: ?>
                    <ul class="row-list">
                        <?php foreach ($archived as $entry): ?>
                            <li class="row-item row-item--stacked">
                                <div class="row-main">
                                    <div class="row-title">
                                        <a href="<?= e(url('/c/' . $category['slug'] . '/e/' . $entry['slug'])) ?>">
                                            <?= e($entry['title']) ?>
                                        </a>
                                        <span class="badge badge--muted"><?= e(t('Archived')) ?></span>
                                    </div>
                                    <div class="row-sub">
                                        <?= e(t('archived %s', human_time($entry['archived_at']))) ?>
                                    </div>
                                </div>

                                <div class="row-actions">
                                    <form method="post"
                                          action="<?= e(url('/c/' . $category['slug'] . '/e/' . $entry['slug'] . '/restore')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="return_to"
                                               value="<?= e(url('/c/' . $category['slug'] . '/archived')) ?>">
                                        <button class="btn btn--sm" type="submit">↩ <?= e(t('Put it back')) ?></button>
                                    </form>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
