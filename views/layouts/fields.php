<?php

use App\FieldTypes;

/**
 * Fields removed from the layout editor land here, to be restored or destroyed for good.
 *
 * @var array $category
 * @var array $layout
 * @var array $fields    live
 * @var array $archived  removed from the layout, content intact
 * @var int   $entryCount
 */
$layoutsViewActive = true;
?>
<div class="columns">
    <?php include APP_ROOT . '/views/partials/entry_list.php'; ?>

    <div class="content-col">
        <div class="page">
            <div class="page-head">
                <div class="crumbs">
                    <?= parent_crumb($category) ?><a href="<?= e(url('/c/' . $category['slug'])) ?>"><?= e($category['name']) ?></a> ›
                    <a href="<?= e(url('/c/' . $category['slug'] . '/layouts')) ?>"><?= e(t('Layouts')) ?></a> ›
                    <a href="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'])) ?>"><?= e($layout['name']) ?></a> ›
                    <span><?= e(t('Fields')) ?></span>
                </div>
                <h1 class="page-title"><?= e(t('Fields')) ?></h1>
                <p class="lede">
                    <?= e(t('Removing a field in the layout editor does not delete anything — it moves the field here, values intact. Deleting for good only happens on this page.')) ?>
                </p>
                <div class="page-actions">
                    <a class="btn" href="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'])) ?>">
                        ✎ <?= e(t('Edit the layout')) ?>
                    </a>
                </div>
            </div>

            <div class="section" style="margin-top:0">
                <h2 class="section-title"><?= e(t('In the layout — %d', count($fields))) ?></h2>
                <?php if ($fields === []): ?>
                    <p class="field-help"><?= e(t('This layout has no fields at the moment.')) ?></p>
                <?php else: ?>
                    <ul class="row-list">
                        <?php foreach ($fields as $field): ?>
                            <li class="row-item">
                                <div class="row-main">
                                    <div class="row-title"><?= e($field['label']) ?></div>
                                    <div class="row-sub">
                                        <?= e(FieldTypes::label((string) $field['field_type'])) ?>
                                        <?php if (!empty($field['help'])): ?>
                                            · <?= e($field['help']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="row-actions">
                                    <span class="badge badge--muted"><?= e(t('Live')) ?></span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2 class="section-title"><?= e(t('Archived — %d', count($archived))) ?></h2>

                <?php if ($archived === []): ?>
                    <p class="field-help">
                        <?= e(t('Nothing archived. Remove a field in the layout editor and it will appear here rather than being lost.')) ?>
                    </p>
                <?php else: ?>
                    <ul class="row-list">
                        <?php foreach ($archived as $field): ?>
                            <?php $held = (int) $field['content_count']; ?>
                            <li class="row-item row-item--stacked">
                                <div class="row-main">
                                    <div class="row-title">
                                        <?= e($field['label']) ?>
                                        <span class="badge badge--muted"><?= e(t('Archived')) ?></span>
                                    </div>
                                    <div class="row-sub">
                                        <?= e(FieldTypes::label((string) $field['field_type'])) ?>
                                        · <?= e(t('removed %s', human_time($field['archived_at']))) ?>
                                        ·
                                        <?php if ($held > 0): ?>
                                            <strong><?= $held ?></strong>
                                            <?= e(tn($held, 'entry still holds', 'entries still hold')) ?>
                                            <?= e(t('content here')) ?>
                                        <?php else: ?>
                                            <?= e(t('holds nothing')) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row-actions">
                                    <form method="post"
                                          action="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'] . '/fields/' . $field['id'] . '/restore')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn--sm" type="submit">↩ <?= e(t('Put it back')) ?></button>
                                    </form>
                                    <button class="btn btn--sm btn--danger" type="button"
                                            data-open-dialog="destroy-field-<?= (int) $field['id'] ?>">
                                        <?= e(t('Delete for good')) ?>
                                    </button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php foreach ($archived as $field): ?>
    <?php $held = (int) $field['content_count']; ?>
    <dialog id="destroy-field-<?= (int) $field['id'] ?>">
        <div class="dialog-body">
            <h2 class="dialog-title"><?= e(t('Delete “%s” for good', $field['label'])) ?></h2>
            <p class="dialog-sub"><?= e(t('This is the only action here that destroys anything.')) ?></p>

            <?php if ($held > 0): ?>
                <div class="notice notice--warn">
                    <strong><?= $held ?></strong>
                    <?= e(tn($held, 'entry has', 'entries have')) ?>
                    <?= e(t('content stored in this field. Deleting it deletes that content. There is no undo — restore the field instead if you are not certain.')) ?>
                </div>
            <?php else: ?>
                <div class="notice">
                    <?= e(t('No entry has anything stored in this field, so nothing will be lost.')) ?>
                </div>
            <?php endif; ?>

            <form method="post"
                  action="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'] . '/fields/' . $field['id'] . '/destroy')) ?>">
                <?= csrf_field() ?>
                <div class="dialog-fields">
                    <div>
                        <label for="confirm-<?= (int) $field['id'] ?>">
                            <?php // %s is markup, not escaped — intentional. ?>
                            <?= t('Type %s to confirm', '<strong>' . e($field['label']) . '</strong>') ?>
                        </label>
                        <input class="input" id="confirm-<?= (int) $field['id'] ?>" type="text"
                               name="confirm_label" autocomplete="off"
                               placeholder="<?= e($field['label']) ?>">
                    </div>
                </div>
                <div class="dialog-foot">
                    <button class="btn btn--ghost" type="button" data-close-dialog><?= e(t('Cancel')) ?></button>
                    <button class="btn btn--danger" type="submit"><?= e(t('Delete the field and its content')) ?></button>
                </div>
            </form>
        </div>
    </dialog>
<?php endforeach; ?>
