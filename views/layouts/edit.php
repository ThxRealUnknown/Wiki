<?php

use App\FieldTypes;
use App\Settings;

/**
 * The layout editor. Each row is one field; the row order is the order the
 * fields appear in on the entry form and on the entry page.
 *
 * @var array $category
 * @var array $layout
 * @var array $fields
 * @var array $allCategories
 * @var int   $entryCount
 */

$types = FieldTypes::all();
$layoutsViewActive = true;
$mapEnabled = Settings::flag(Settings::FEATURE_MAP);

// New rows omit disabled Map types; existing rows keep the full list so their
// current type still has a matching option — otherwise the browser would
// silently select something else, and that's what gets saved.
$addableTypes = $types;
if (!$mapEnabled) {
    unset($addableTypes[FieldTypes::MAPAREA], $addableTypes[FieldTypes::MAPPOINT], $addableTypes[FieldTypes::MAPPATH]);
}
?>
<div class="columns">
    <?php include APP_ROOT . '/views/partials/entry_list.php'; ?>

    <div class="content-col">
        <form class="page page--wide" method="post"
              action="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'] . '/update')) ?>"
              data-layout-editor>
            <?= csrf_field() ?>

            <div class="page-head">
                <div class="crumbs">
                    <?= parent_crumb($category) ?><a href="<?= e(url('/c/' . $category['slug'])) ?>"><?= e($category['name']) ?></a> ›
                    <a href="<?= e(url('/c/' . $category['slug'] . '/layouts')) ?>"><?= e(t('Layouts')) ?></a> ›
                    <span><?= e($layout['name']) ?></span>
                </div>

                <input class="input input--title" type="text" name="name"
                       value="<?= e($layout['name']) ?>" required>

                <div class="page-meta" style="margin-top:12px">
                    <?php if ((int) $layout['is_default'] === 1): ?>
                        <span class="badge"><?= e(t('Default for new entries')) ?></span>
                    <?php endif; ?>
                    <span><?= e(tn($entryCount, '%d entry uses this layout', '%d entries use this layout')) ?></span>
                </div>
            </div>

            <?php if ($entryCount > 0): ?>
                <div class="notice">
                    <?php // %s is markup, not escaped — intentional. ?>
                    <?= tn($entryCount,
                        'Changing this layout changes all %d entry built from it. Renaming a field keeps its values; removing one <strong>archives</strong> it — put it back anytime from %s.',
                        'Changing this layout changes all %d entries built from it. Renaming a field keeps its values; removing one <strong>archives</strong> it — put it back anytime from %s.',
                        '<a href="' . e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'] . '/fields')) . '">' . e(t('Fields')) . '</a>'
                    ) ?>
                </div>
            <?php endif; ?>

            <?php if (($archived ?? []) !== []): ?>
                <p class="field-help" style="margin:-8px 0 16px">
                    <?php // %s is markup, not escaped — intentional. ?>
                    <?= tn(count($archived),
                        '%d archived field is being kept out of this layout — %s.',
                        '%d archived fields are being kept out of this layout — %s.',
                        '<a href="' . e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'] . '/fields')) . '">' . e(t('review them')) . '</a>'
                    ) ?>
                </p>
            <?php endif; ?>

            <ul class="field-rows" data-field-rows>
                <?php foreach ($fields as $index => $field): ?>
                    <?php
                    $config = $field['config'] ?? [];
                    $rowIndex = $index;
                    $rowIsMapField = in_array($field['field_type'], [FieldTypes::MAPAREA, FieldTypes::MAPPOINT, FieldTypes::MAPPATH], true);
                    ?>
                    <li class="field-row" data-field-row draggable="false"
                        <?= $rowIsMapField && !$mapEnabled ? 'hidden' : '' ?>>
                        <input type="hidden" name="fields[<?= $rowIndex ?>][id]" value="<?= (int) $field['id'] ?>"
                               data-name-template="fields[__i__][id]">

                        <div class="field-row-head">
                            <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>

                            <span class="field-row-label">
                                <input type="text" name="fields[<?= $rowIndex ?>][label]"
                                       value="<?= e($field['label']) ?>" placeholder="<?= e(t('Field name')) ?>"
                                       data-name-template="fields[__i__][label]">
                            </span>

                            <span class="field-row-type">
                                <select name="fields[<?= $rowIndex ?>][field_type]" data-field-type
                                        data-name-template="fields[__i__][field_type]">
                                    <?php foreach ($types as $typeKey => $meta): ?>
                                        <option value="<?= e($typeKey) ?>"
                                            <?= $field['field_type'] === $typeKey ? 'selected' : '' ?>>
                                            <?= e($meta['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </span>

                            <button type="button" class="icon-btn" data-toggle-row title="<?= e(t('More options')) ?>">⋯</button>
                            <button type="button" class="icon-btn" data-remove-row
                                    title="<?= e(t('Take this field out of the layout (its content is kept)')) ?>">✕</button>
                        </div>

                        <div class="field-row-body">
                            <div class="field-opt">
                                <label><?= e(t('Help text')) ?></label>
                                <input class="input" type="text" name="fields[<?= $rowIndex ?>][help]"
                                       value="<?= e($field['help'] ?? '') ?>"
                                       placeholder="<?= e(t('Shown under the field while editing')) ?>"
                                       data-name-template="fields[__i__][help]">
                            </div>

                            <div class="field-opt" style="flex:0 0 130px">
                                <label><?= e(t('Width')) ?></label>
                                <select class="select" name="fields[<?= $rowIndex ?>][width]"
                                        data-name-template="fields[__i__][width]">
                                    <option value="full" <?= ($field['width'] ?? 'full') === 'full' ? 'selected' : '' ?>><?= e(t('Full row')) ?></option>
                                    <option value="half" <?= ($field['width'] ?? 'full') === 'half' ? 'selected' : '' ?>><?= e(t('Half row')) ?></option>
                                </select>
                            </div>

                            <!-- options list: choice + tags -->
                            <div class="field-opt" data-opt-for="select,tags">
                                <label><?= e(t('Options — one per line')) ?></label>
                                <textarea class="textarea" name="fields[<?= $rowIndex ?>][config][options]"
                                          data-name-template="fields[__i__][config][options]"
                                          placeholder="Alive&#10;Dead&#10;Unknown"><?= e(implode("\n", $config['options'] ?? [])) ?></textarea>
                            </div>

                            <div class="field-opt" data-opt-for="tags" style="flex:0 0 200px">
                                <label><?= e(t('Free entry')) ?></label>
                                <label class="checkbox-row">
                                    <input type="checkbox" value="1"
                                           name="fields[<?= $rowIndex ?>][config][allow_custom]"
                                           data-name-template="fields[__i__][config][allow_custom]"
                                        <?= !empty($config['allow_custom']) ? 'checked' : '' ?>>
                                    <?= e(t('Allow tags outside the list')) ?>
                                </label>
                            </div>

                            <div class="field-opt" data-opt-for="select" style="flex:0 0 230px">
                                <label><?= e(t('Sorting')) ?></label>
                                <label class="checkbox-row">
                                    <input type="checkbox" value="1"
                                           name="fields[<?= $rowIndex ?>][config][sortable]"
                                           data-name-template="fields[__i__][config][sortable]"
                                        <?= !empty($config['sortable']) ? 'checked' : '' ?>>
                                    <?= e(t('Sort the entry list by this')) ?>
                                </label>
                                <p class="type-hint">
                                    <?= e(t('Adds it to the sort menu above the entry list. Entries follow the option order written on the left, then A–Z within each.')) ?>
                                </p>
                            </div>

                            <!-- relation -->
                            <div class="field-opt" data-opt-for="relation">
                                <label><?= e(t('Links to which archives')) ?></label>
                                <?php $chosenTargets = FieldTypes::relationTargets($config); ?>
                                <div class="target-list">
                                    <?php foreach ($allCategories as $target): ?>
                                        <label class="checkbox-row"
                                               style="--depth: <?= (int) ($target['depth'] ?? 0) ?>">
                                            <input type="checkbox"
                                                   name="fields[<?= $rowIndex ?>][config][target_category_ids][]"
                                                   data-name-template="fields[__i__][config][target_category_ids][]"
                                                   value="<?= (int) $target['id'] ?>"
                                                <?= in_array((int) $target['id'], $chosenTargets, true) ? 'checked' : '' ?>>
                                            <?= e($target['icon'] ?: '•') ?> <?= e($target['name']) ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="type-hint">
                                    <?= e(t('Tick which archives should be able to be linked.')) ?>
                                </p>
                            </div>

                            <div class="field-opt" data-opt-for="relation" style="flex:0 0 200px">
                                <label><?= e(t('How many')) ?></label>
                                <label class="checkbox-row">
                                    <input type="checkbox" value="1"
                                           name="fields[<?= $rowIndex ?>][config][multiple]"
                                           data-name-template="fields[__i__][config][multiple]"
                                        <?= !empty($config['multiple']) ? 'checked' : '' ?>>
                                    <?= e(t('Allow several links')) ?>
                                </label>
                            </div>

                            <div class="field-opt" data-opt-for="relation" style="flex:0 0 200px">
                                <label><?= e(t('Typing')) ?></label>
                                <label class="checkbox-row">
                                    <input type="checkbox" value="1"
                                           name="fields[<?= $rowIndex ?>][config][typed]"
                                           data-name-template="fields[__i__][config][typed]"
                                           data-relation-typed-toggle
                                        <?= !empty($config['typed']) ? 'checked' : '' ?>>
                                    <?= e(t('Enable relation typing')) ?>
                                </label>
                            </div>

                            <div class="field-opt" data-opt-for="relation" data-relation-types-opt
                                 <?= empty($config['typed']) ? 'style="display:none"' : '' ?>>
                                <label><?= e(t('Types — one per line')) ?></label>
                                <textarea class="textarea" name="fields[<?= $rowIndex ?>][config][types]"
                                          data-name-template="fields[__i__][config][types]"
                                          placeholder="Mother&#10;Brother&#10;Sister"><?= e(implode("\n", $config['types'] ?? [])) ?></textarea>
                                <p class="type-hint">
                                    <?= e(t('Each link picked in this field can be given one of these labels.')) ?>
                                </p>
                            </div>

                            <!-- number -->
                            <div class="field-opt" data-opt-for="number" style="flex:0 0 200px">
                                <label><?= e(t('Unit')) ?></label>
                                <input class="input" type="text" name="fields[<?= $rowIndex ?>][config][unit]"
                                       value="<?= e($config['unit'] ?? '') ?>" placeholder="<?= e(t('years, cm, people')) ?>"
                                       data-name-template="fields[__i__][config][unit]">
                            </div>

                            <!-- placeholder -->
                            <div class="field-opt" data-opt-for="text,textarea">
                                <label><?= e(t('Placeholder')) ?></label>
                                <input class="input" type="text" name="fields[<?= $rowIndex ?>][config][placeholder]"
                                       value="<?= e($config['placeholder'] ?? '') ?>"
                                       data-name-template="fields[__i__][config][placeholder]">
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($fields === []): ?>
                <p class="field-help" data-empty-hint><?= e(t('No fields yet — add the first one below.')) ?></p>
            <?php endif; ?>

            <div style="margin-top:14px">
                <button type="button" class="btn" data-add-field>＋ <?= e(t('Add field')) ?></button>
            </div>

            <div class="form-bar">
                <button class="btn btn--primary" type="submit"><?= e(t('Save layout')) ?></button>
                <a class="btn btn--ghost" href="<?= e(url('/c/' . $category['slug'] . '/layouts')) ?>"><?= e(t('Back to layouts')) ?></a>
                <span class="spacer"></span>
                <span class="field-help" style="margin:0" data-dirty-hint hidden><?= e(t('Unsaved changes')) ?></span>
            </div>
        </form>

        <div class="page page--wide" style="padding-top:0">
            <div class="section" style="margin-top:8px">
                <h2 class="section-title"><?= e(t('This layout')) ?></h2>
                <div class="row-item">
                    <div class="row-main">
                        <div class="row-title"><?= e($layout['name']) ?></div>
                        <div class="row-sub">
                            <?php if ((int) $layout['is_default'] === 1): ?>
                                <?= e(t('Used for every new entry in %s.', $category['name'])) ?>
                            <?php else: ?>
                                <?= e(t('Not the default — pick it manually when creating an entry.')) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row-actions">
                        <?php if ((int) $layout['is_default'] !== 1): ?>
                            <form method="post"
                                  action="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'] . '/default')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn--sm" type="submit"><?= e(t('Make default')) ?></button>
                            </form>
                        <?php endif; ?>
                        <form method="post"
                              action="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'] . '/duplicate')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn--sm" type="submit"><?= e(t('Duplicate')) ?></button>
                        </form>
                        <form method="post"
                              action="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'] . '/delete')) ?>"
                              data-confirm="<?= e(t('Delete the layout “%s”?', $layout['name'])) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn--sm btn--danger" type="submit"
                                <?= $entryCount > 0 ? 'disabled title="' . e(t('Entries still use this layout')) . '"' : '' ?>>
                                <?= e(t('Delete')) ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Blueprint for a new field row, cloned by app.js. -->
<template data-field-template>
    <li class="field-row is-open" data-field-row>
        <input type="hidden" name="fields[__i__][id]" value="" data-name-template="fields[__i__][id]">
        <div class="field-row-head">
            <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>
            <span class="field-row-label">
                <input type="text" name="fields[__i__][label]" value="" placeholder="<?= e(t('Field name')) ?>"
                       data-name-template="fields[__i__][label]">
            </span>
            <span class="field-row-type">
                <select name="fields[__i__][field_type]" data-field-type
                        data-name-template="fields[__i__][field_type]">
                    <?php foreach ($addableTypes as $typeKey => $meta): ?>
                        <option value="<?= e($typeKey) ?>"><?= e($meta['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </span>
            <button type="button" class="icon-btn" data-toggle-row title="<?= e(t('More options')) ?>">⋯</button>
            <button type="button" class="icon-btn icon-btn--danger" data-remove-row title="<?= e(t('Remove field')) ?>">🗑</button>
        </div>
        <div class="field-row-body">
            <div class="field-opt">
                <label><?= e(t('Help text')) ?></label>
                <input class="input" type="text" name="fields[__i__][help]"
                       placeholder="<?= e(t('Shown under the field while editing')) ?>"
                       data-name-template="fields[__i__][help]">
            </div>
            <div class="field-opt" style="flex:0 0 130px">
                <label><?= e(t('Width')) ?></label>
                <select class="select" name="fields[__i__][width]" data-name-template="fields[__i__][width]">
                    <option value="full"><?= e(t('Full row')) ?></option>
                    <option value="half"><?= e(t('Half row')) ?></option>
                </select>
            </div>
            <div class="field-opt" data-opt-for="select,tags">
                <label><?= e(t('Options — one per line')) ?></label>
                <textarea class="textarea" name="fields[__i__][config][options]"
                          data-name-template="fields[__i__][config][options]"></textarea>
            </div>
            <div class="field-opt" data-opt-for="tags" style="flex:0 0 200px">
                <label><?= e(t('Free entry')) ?></label>
                <label class="checkbox-row">
                    <input type="checkbox" value="1" name="fields[__i__][config][allow_custom]"
                           data-name-template="fields[__i__][config][allow_custom]" checked>
                    <?= e(t('Allow tags outside the list')) ?>
                </label>
            </div>
            <div class="field-opt" data-opt-for="select" style="flex:0 0 230px">
                <label><?= e(t('Sorting')) ?></label>
                <label class="checkbox-row">
                    <input type="checkbox" value="1" name="fields[__i__][config][sortable]"
                           data-name-template="fields[__i__][config][sortable]">
                    <?= e(t('Sort the entry list by this')) ?>
                </label>
                <p class="type-hint">
                    <?= e(t('Adds it to the sort menu above the entry list. Entries follow the option order written on the left, then A–Z within each.')) ?>
                </p>
            </div>
            <div class="field-opt" data-opt-for="relation">
                <label><?= e(t('Links to which archives')) ?></label>
                <div class="target-list">
                    <?php foreach ($allCategories as $target): ?>
                        <label class="checkbox-row" style="--depth: <?= (int) ($target['depth'] ?? 0) ?>">
                            <input type="checkbox"
                                   name="fields[__i__][config][target_category_ids][]"
                                   data-name-template="fields[__i__][config][target_category_ids][]"
                                   value="<?= (int) $target['id'] ?>">
                            <?= e($target['icon'] ?: '•') ?> <?= e($target['name']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="type-hint"><?= e(t('Tick none to allow any archive.')) ?></p>
            </div>
            <div class="field-opt" data-opt-for="relation" style="flex:0 0 200px">
                <label><?= e(t('How many')) ?></label>
                <label class="checkbox-row">
                    <input type="checkbox" value="1" name="fields[__i__][config][multiple]"
                           data-name-template="fields[__i__][config][multiple]">
                    <?= e(t('Allow several links')) ?>
                </label>
            </div>
            <div class="field-opt" data-opt-for="relation" style="flex:0 0 200px">
                <label><?= e(t('Typing')) ?></label>
                <label class="checkbox-row">
                    <input type="checkbox" value="1" name="fields[__i__][config][typed]"
                           data-name-template="fields[__i__][config][typed]" data-relation-typed-toggle>
                    <?= e(t('Enable relation typing')) ?>
                </label>
            </div>
            <div class="field-opt" data-opt-for="relation" data-relation-types-opt style="display:none">
                <label><?= e(t('Types — one per line')) ?></label>
                <textarea class="textarea" name="fields[__i__][config][types]"
                          data-name-template="fields[__i__][config][types]"
                          placeholder="Mother&#10;Brother&#10;Sister"></textarea>
                <p class="type-hint">
                    <?= e(t('Each link picked in this field can be given one of these labels.')) ?>
                </p>
            </div>
            <div class="field-opt" data-opt-for="number" style="flex:0 0 200px">
                <label><?= e(t('Unit')) ?></label>
                <input class="input" type="text" name="fields[__i__][config][unit]"
                       placeholder="<?= e(t('years, cm, people')) ?>" data-name-template="fields[__i__][config][unit]">
            </div>
            <div class="field-opt" data-opt-for="text,textarea">
                <label><?= e(t('Placeholder')) ?></label>
                <input class="input" type="text" name="fields[__i__][config][placeholder]"
                       data-name-template="fields[__i__][config][placeholder]">
            </div>
        </div>
    </li>
</template>
