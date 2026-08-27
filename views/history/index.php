<?php
/**
 * Every entry edit and deletion on record, newest first.
 *
 * @var array $revisions one page of rows from EntryRevisionRepo::recent()
 * @var int   $total
 * @var int   $page
 * @var int   $pages
 */
?>
<div class="single-col">
    <div class="page">
        <div class="page-head">
            <div class="crumbs"><span><?= e(t('Change history')) ?></span></div>
            <h1 class="page-title"><?= e(t('Change history')) ?></h1>
            <p class="lede">
                <?= e(t('Every entry edit and deletion.')) ?>
            </p>
            <?php if ($total > 0): ?>
                <div class="page-actions">
                    <form method="post" action="<?= e(url('/history/clear')) ?>"
                          <?php // &#10; is a raw HTML entity, not escaped — intentional. ?>
                          data-confirm="<?= t('Clear all change history?&#10;&#10;Every recorded edit and deletion will be gone for good, and none of it can be restored afterward. This cannot be undone.') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn--sm btn--danger" type="submit"><?= e(t('Clear history')) ?></button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($revisions === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon">🕓</span>
                <h3><?= e(t('Nothing recorded yet')) ?></h3>
                <p><?= e(t('Editing or deleting an entry will start building history here.')) ?></p>
            </div>
        <?php else: ?>
            <ul class="row-list">
                <?php foreach ($revisions as $revision): ?>
                    <?php
                    $isDelete = $revision['kind'] === 'delete';
                    $stillThere = $revision['entry_id'] !== null && $revision['entry_slug'] !== null;
                    ?>
                    <li class="row-item">
                        <span class="badge<?= $isDelete ? ' badge--muted' : '' ?>">
                            <?= $isDelete ? e(t('Deleted')) : e(t('Edited')) ?>
                        </span>
                        <div class="row-main">
                            <div class="row-title">
                                <?php if ($stillThere): ?>
                                    <a href="<?= e(url('/c/' . $revision['category_slug'] . '/e/' . $revision['entry_slug'])) ?>">
                                        <?= e($revision['title']) ?>
                                    </a>
                                <?php else: ?>
                                    <?= e($revision['title']) ?>
                                <?php endif; ?>
                            </div>
                            <div class="row-sub">
                                <?= e($revision['category_name'] ?? t('Unknown archive')) ?>
                                · <?= e(human_time($revision['created_at'])) ?>
                            </div>
                        </div>
                        <div class="row-actions">
                            <a class="btn btn--sm btn--ghost"
                               href="<?= e(url('/history/' . (int) $revision['id'] . '/diff')) ?>">
                                <?= e(t('What changed')) ?>
                            </a>
                            <form method="post"
                                  action="<?= e(url('/history/' . (int) $revision['id'] . '/restore')) ?>"
                                  data-confirm="<?= e(t("Restore this version? The current content will be replaced — but it's saved to history first, so this can be undone too.")) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn--sm" type="submit"><?= e(t('Restore this version')) ?></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($pages > 1): ?>
                <?php
                $pageUrl = static fn (int $target): string => url(
                    '/history' . ($target > 1 ? '?page=' . $target : '')
                );
                ?>
                <nav class="pager">
                    <a class="pager-step<?= $page <= 1 ? ' is-off' : '' ?>"
                       href="<?= e($pageUrl(max(1, $page - 1))) ?>"
                        <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>←</a>
                    <span class="pager-pos"><?= $page ?> / <?= $pages ?></span>
                    <a class="pager-step<?= $page >= $pages ? ' is-off' : '' ?>"
                       href="<?= e($pageUrl(min($pages, $page + 1))) ?>"
                        <?= $page >= $pages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>→</a>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
