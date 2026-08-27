<?php
/**
 * What one recorded edit actually changed, against whatever came right after
 * it — the next edit, or the entry as it stands now.
 *
 * @var array $diff from EntryRevisionRepo::diff()
 */
$revision = $diff['revision'];
$isDelete = $revision['kind'] === 'delete';
$hasChanges = $diff['fields'] !== [] || $diff['title_changed'];
?>
<div class="single-col">
    <div class="page">
        <div class="page-head">
            <div class="crumbs">
                <a href="<?= e(url('/history')) ?>"><?= e(t('Change history')) ?></a> ›
                <span><?= e(t('What changed')) ?></span>
            </div>
            <h1 class="page-title"><?= e($diff['title_before']) ?></h1>
            <p class="lede">
                <?= $isDelete ? e(t('Deleted')) : e(t('Edited')) ?> <?= e(human_time($revision['created_at'])) ?>
                <?php if ($diff['entry_gone']): ?>
                    — <?= e(t('the entry does not exist any more.')) ?>
                <?php endif; ?>
            </p>
        </div>

        <?php if (!$hasChanges): ?>
            <div class="empty-state">
                <span class="empty-state-icon">🕓</span>
                <h3><?= e(t('Nothing to compare')) ?></h3>
                <p><?= e(t('This version is identical to whatever came right after it.')) ?></p>
            </div>
        <?php else: ?>
            <div class="diff-table">
                <div class="diff-head">
                    <div class="diff-label"></div>
                    <div><?= e(t('Before')) ?></div>
                    <div><?= $diff['entry_gone'] ? e(t('After (entry deleted)')) : e(t('After')) ?></div>
                </div>

                <?php if ($diff['title_changed']): ?>
                    <div class="diff-row">
                        <div class="diff-label"><?= e(t('Title')) ?></div>
                        <div class="diff-old"><?= e($diff['title_before']) ?></div>
                        <div class="diff-new"><?= e((string) $diff['title_after']) ?></div>
                    </div>
                <?php endif; ?>

                <?php foreach ($diff['fields'] as $field): ?>
                    <div class="diff-row">
                        <div class="diff-label"><?= e($field['label']) ?></div>
                        <?php if ($field['kind'] === 'links'): ?>
                            <div class="diff-old">
                                <?= $field['old'] === [] ? '—' : e(implode(', ', $field['old'])) ?>
                            </div>
                            <div class="diff-new">
                                <?= $field['new'] === [] ? '—' : e(implode(', ', $field['new'])) ?>
                            </div>
                        <?php else: ?>
                            <div class="diff-old"><?= $field['old'] === '' ? '—' : e($field['old']) ?></div>
                            <div class="diff-new"><?= $field['new'] === '' ? '—' : e($field['new']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="page-actions">
            <a class="btn" href="<?= e(url('/history')) ?>">← <?= e(t('Back to Change history')) ?></a>
            <form method="post" action="<?= e(url('/history/' . (int) $revision['id'] . '/restore')) ?>"
                  data-confirm="<?= e(t("Restore this version? The current content will be replaced — but it's saved to history first, so this can be undone too.")) ?>">
                <?= csrf_field() ?>
                <button class="btn btn--primary" type="submit"><?= e(t('Restore this version')) ?></button>
            </form>
        </div>
    </div>
</div>
