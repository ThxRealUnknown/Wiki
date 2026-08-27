<?php
/**
 * Create / edit an entry — the fields come from the layout, not hardcoded here.
 *
 * @var array      $category
 * @var array|null $entry   null when creating
 * @var array      $layout
 * @var array      $layouts every layout in this archive
 * @var array      $fields
 * @var array      $values
 * @var array      $links
 */

$isNew = $entry === null;
$action = $isNew
    ? url('/c/' . $category['slug'] . '/create')
    : url('/c/' . $category['slug'] . '/e/' . $entry['slug'] . '/update');
?>
<div class="columns">
    <?php include APP_ROOT . '/views/partials/entry_list.php'; ?>

    <div class="content-col">
        <form class="page" method="post" action="<?= e($action) ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="page-head">
                <div class="crumbs">
                    <?= parent_crumb($category) ?><a href="<?= e(url('/c/' . $category['slug'])) ?>"><?= e($category['name']) ?></a> ›
                    <span><?= $isNew ? e(t('New entry')) : e(t('Editing')) ?></span>
                </div>

                <input class="input input--title" type="text" name="title" required
                       value="<?= e($isNew ? '' : $entry['title']) ?>"
                       placeholder="<?= e(t('Name…')) ?>" autocomplete="off"
                       <?= $isNew ? 'autofocus' : '' ?>>

                <div class="page-meta" style="margin-top:12px">
                    <label for="layout-picker" style="color:var(--text-faint)"><?= e(t('Layout')) ?></label>
                    <select class="select" id="layout-picker" name="layout_id"
                            style="width:auto; min-width:190px" data-layout-switch
                            data-base="<?= e($isNew
                                ? url('/c/' . $category['slug'] . '/new')
                                : url('/c/' . $category['slug'] . '/e/' . $entry['slug'] . '/edit')) ?>"
                            <?= $isNew ? '' : 'data-warn="1"' ?>>
                        <?php foreach ($layouts as $option): ?>
                            <option value="<?= (int) $option['id'] ?>"
                                <?= (int) $option['id'] === (int) $layout['id'] ? 'selected' : '' ?>>
                                <?= e($option['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <a class="badge badge--muted"
                       href="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'])) ?>">
                        <?= e(t('Edit its fields')) ?>
                    </a>
                </div>
            </div>

            <?php if ($fields === []): ?>
                <div class="notice">
                    <?php // the translated value embeds its own <a href="%s"> — not escaped, intentional. ?>
                    <?= t('“%s” has no fields yet, so this entry can only have a name. <a href="%s">Add some fields</a> and they will show up here.',
                        e($layout['name']),
                        e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id']))) ?>
                </div>
            <?php else: ?>
                <?php // One copy for every Date/Era picker on the page, instead of repeating per field. ?>
                <script type="application/json" data-calendar-config>
                    <?= json_encode(App\Calendar::config(), JSON_UNESCAPED_UNICODE) ?>
                </script>
                <div class="field-grid">
                    <?php foreach ($fields as $field): ?>
                        <?php include APP_ROOT . '/views/entries/_field_input.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="form-bar">
                <button class="btn btn--primary" type="submit">
                    <?= $isNew ? e(t('Create entry')) : e(t('Save changes')) ?>
                </button>
                <a class="btn btn--ghost"
                   href="<?= e($isNew
                       ? url('/c/' . $category['slug'])
                       : url('/c/' . $category['slug'] . '/e/' . $entry['slug'])) ?>"><?= e(t('Cancel')) ?></a>
                <span class="spacer"></span>
                <?php if (!$isNew): ?>
                    <span class="field-help" style="margin:0">
                        <?= e(t('Last edited %s', human_time($entry['updated_at']))) ?>
                    </span>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
