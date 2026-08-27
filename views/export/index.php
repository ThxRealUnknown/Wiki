<?php
/**
 * The export page: one card for the world, one for the book.
 *
 * @var array $formats
 * @var int   $archiveCount
 * @var int   $entryCount
 * @var int   $chapterCount
 * @var int   $visibleCount
 */
?>
<div class="single-col">
    <div class="page">
        <div class="page-head">
            <h1 class="page-title"><?= e(t('Export')) ?></h1>
            <p class="lede">
                <?= e(t('Save everything to a file on this computer.')) ?>
            </p>
        </div>

        <div class="export-grid">

            <form class="export-card" method="get" action="<?= e(url('/export/wiki')) ?>">
                <div class="export-head">
                    <span class="export-icon">🗂</span>
                    <div>
                        <h2 class="export-title"><?= e(t('The world')) ?></h2>
                        <p class="export-sub">
                            <?= e(t('%d archives · %d entries', (int) $archiveCount, (int) $entryCount)) ?>
                        </p>
                    </div>
                </div>

                <p class="export-desc">
                    <?= e(t("Every archive and every entry, in sidebar order, with sub-archives following their parent. Each entry keeps its layout's fields, and History stays in chronological order.")) ?>
                </p>

                <div class="export-options">
                    <label class="field-label" for="wiki-format"><?= e(t('Format')) ?></label>
                    <select class="select" id="wiki-format" name="format" data-format-picker>
                        <?php foreach ($formats as $value => $format): ?>
                            <option value="<?= e($value) ?>" data-hint="<?= e($format['hint']) ?>">
                                <?= e($format['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-help" data-format-hint></p>

                    <label class="checkbox-row">
                        <input type="checkbox" name="connections" value="1" checked>
                        <?= e(t('Include connections and backlinks')) ?>
                    </label>
                    <label class="checkbox-row">
                        <input type="checkbox" name="empty_fields" value="1">
                        <?= e(t('Include fields that are empty')) ?>
                    </label>
                </div>

                <button class="btn btn--primary btn--block" type="submit">⭳ <?= e(t('Export the world')) ?></button>
            </form>

            <form class="export-card" method="get" action="<?= e(url('/export/book')) ?>">
                <div class="export-head">
                    <span class="export-icon">📕</span>
                    <div>
                        <h2 class="export-title"><?= e(t('The book')) ?></h2>
                        <p class="export-sub">
                            <?= e(t('%d shown', (int) $visibleCount)) ?>
                            <?php if ($chapterCount > $visibleCount): ?>
                                · <?= e(t('%d hidden', (int) ($chapterCount - $visibleCount))) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>

                <p class="export-desc">
                    <?= e(t('The chapters in reading order, as the Story presents them — title, number and text, with a word count on the cover. Notes stay out unless you ask for them.')) ?>
                </p>

                <div class="export-options">
                    <label class="field-label" for="book-format"><?= e(t('Format')) ?></label>
                    <select class="select" id="book-format" name="format" data-format-picker>
                        <?php foreach ($formats as $value => $format): ?>
                            <option value="<?= e($value) ?>" data-hint="<?= e($format['hint']) ?>">
                                <?= e($format['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-help" data-format-hint></p>

                    <label class="checkbox-row">
                        <input type="checkbox" name="hidden" value="1">
                        <?= e(t('Include chapters hidden from the Story')) ?>
                    </label>
                    <label class="checkbox-row">
                        <input type="checkbox" name="notes" value="1">
                        <?= e(t('Include your notes')) ?>
                    </label>
                </div>

                <button class="btn btn--primary btn--block" type="submit"
                    <?= $chapterCount === 0 ? 'disabled title="' . e(t('There are no chapters yet')) . '"' : '' ?>>
                    ⭳ <?= e(t('Export the book')) ?>
                </button>
            </form>

        </div>

        <div class="section">
            <h2 class="section-title"><?= e(t('Backup and restore')) ?></h2>

            <div class="export-grid">
                <form class="export-card" method="get" action="<?= e(url('/export/backup')) ?>">
                    <div class="export-head">
                        <span class="export-icon">🛟</span>
                        <div>
                            <h2 class="export-title"><?= e(t('Full backup')) ?></h2>
                            <p class="export-sub"><?= e(t('Everything, as JSON')) ?></p>
                        </div>
                    </div>
                    <p class="export-desc">
                        <?= e(t('Archives, layouts, every field — archived ones included — entries, connections and chapters. Unlike the documents above, this can be read back in, so it is the one to keep somewhere safe.')) ?>
                    </p>
                    <p class="field-help" style="margin:0">
                        <?php // %s is markup, not escaped — intentional. ?>
                        <?= t('Uploaded images are %s inside the file. Keep a copy of <code>public/uploads</code> alongside it.',
                            '<strong>' . e(t('not')) . '</strong>') ?>
                    </p>
                    <button class="btn btn--primary btn--block" type="submit">⭳ <?= e(t('Download backup')) ?></button>
                </form>

                <form class="export-card" method="post" action="<?= e(url('/export/import')) ?>"
                      enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="export-head">
                        <span class="export-icon">⭱</span>
                        <div>
                            <h2 class="export-title"><?= e(t('Restore')) ?></h2>
                            <p class="export-sub"><?= e(t('From a backup file')) ?></p>
                        </div>
                    </div>
                    <p class="export-desc">
                        <?= e(t('Reads a backup back in. Anything it recognises is updated in place; anything new is added. Nothing is deleted — entries you have made since the backup stay exactly where they are.')) ?>
                    </p>
                    <div class="export-options">
                        <label class="field-label" for="backup-file"><?= e(t('Backup file')) ?></label>
                        <input class="file-input" id="backup-file" type="file" name="backup"
                               accept="application/json,.json" required>
                        <p class="field-help">
                            <?= e(t('You will see exactly what it would change before anything is written.')) ?>
                        </p>
                    </div>
                    <button class="btn btn--block" type="submit"><?= e(t('Review a restore…')) ?></button>
                </form>
            </div>
        </div>

        <div class="section">
            <h2 class="section-title"><?= e(t('About the formats')) ?></h2>
            <ul class="row-list">
                <?php foreach ($formats as $format): ?>
                    <li class="row-item">
                        <div class="row-main">
                            <div class="row-title"><?= e($format['label']) ?></div>
                            <div class="row-sub"><?= e($format['hint']) ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="field-help" style="margin-top:12px">
                <?= e(t("For a PDF, export the web page and use your browser's Print → Save as PDF.")) ?>
            </p>
        </div>
    </div>
</div>
