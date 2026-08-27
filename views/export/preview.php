<?php
/**
 * What a restore would do — computed by actually running it, then rolling back.
 *
 * @var array $tally
 * @var array $staged file name and size
 */

$groups = [
    'Archives' => ['categories_created', 'categories_updated'],
    'Layouts'  => ['layouts_created', 'layouts_updated'],
    'Fields'   => ['fields_created', 'fields_updated'],
    'Entries'  => ['entries_created', 'entries_updated'],
    'Chapters' => ['chapters_created', 'chapters_updated'],
];

$created = 0;
$updated = 0;
foreach ($groups as [$c, $u]) {
    $created += (int) $tally[$c];
    $updated += (int) $tally[$u];
}
?>
<div class="single-col">
    <div class="page">
        <div class="page-head">
            <div class="crumbs">
                <a href="<?= e(url('/export')) ?>"><?= e(t('Export')) ?></a> ›
                <span><?= e(t('Restore')) ?></span>
            </div>
            <h1 class="page-title"><?= e(t('Review this restore')) ?></h1>
            <p class="lede">
                <strong><?= e($staged['name']) ?></strong> —
                <?= e(t('%s KB. Nothing has been written yet.', number_format($staged['bytes'] / 1024, 0))) ?>
            </p>
        </div>

        <div class="notice">
            <?php // %s×3 are markup, not escaped — intentional. ?>
            <?= t('%s to create, %s to update, %s connections to add. Nothing is deleted by a restore.',
                '<strong>' . $created . '</strong>',
                '<strong>' . $updated . '</strong>',
                '<strong>' . (int) $tally['connections_added'] . '</strong>') ?>
        </div>

        <ul class="row-list">
            <?php foreach ($groups as $label => [$createdKey, $updatedKey]): ?>
                <li class="row-item">
                    <div class="row-main">
                        <div class="row-title"><?= e(t($label)) ?></div>
                        <div class="row-sub">
                            <?= e(t('%d new', (int) $tally[$createdKey])) ?>
                            · <?= e(t('%d already here, will be overwritten', (int) $tally[$updatedKey])) ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ((int) $tally['skipped'] > 0): ?>
            <div class="notice notice--warn" style="margin-top:16px">
                <?= e(t('%d record(s) in the file could not be read and will be skipped — usually because the file was edited by hand or written by a different tool.',
                    (int) $tally['skipped'])) ?>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2 class="section-title"><?= e(t('What "overwritten" means')) ?></h2>
            <p class="field-help" style="max-width:62ch">
                <?= e(t("Records are matched on a hidden identifier, not on their name — a renamed entry in the file will still overwrite its match here. Entries not in the file are left alone. If you'd rather not lose recent edits, download a backup first.")) ?>
            </p>
        </div>

        <div class="form-bar">
            <form method="post" action="<?= e(url('/export/import/apply')) ?>"
                  data-confirm="<?= e(t('Apply this restore? %d existing record(s) will be overwritten.', $updated)) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($staged['token']) ?>">
                <button class="btn btn--primary" type="submit"><?= e(t('Apply the restore')) ?></button>
            </form>
            <a class="btn btn--ghost" href="<?= e(url('/export')) ?>"><?= e(t('Cancel')) ?></a>
        </div>
    </div>
</div>
