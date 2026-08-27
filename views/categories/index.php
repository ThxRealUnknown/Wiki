<?php

use App\CategoryRepo;

/**
 * Create, rename, recolour, nest and delete archives.
 *
 * @var array $tree       top-level archives, each with a 'children' key
 * @var array $categories the same archives flat, for the edit dialogs
 * @var array $parents    archives that are allowed to be a parent
 */

$palette = ['#c98a4b', '#5f9e6e', '#7b6cd9', '#4b8fc9', '#c9576b', '#b58ad6', '#4fa8a0', '#a8894b'];
$repo = new CategoryRepo();
$rows = [];
$flatten = static function (array $nodes, int $depth) use (&$flatten, &$rows): void {
    foreach ($nodes as $node) {
        $rows[] = ['category' => $node, 'depth' => $depth];
        $flatten($node['children'], $depth + 1);
    }
};
$flatten($tree, 0);
?>
<div class="single-col">
    <div class="page">
        <div class="page-head">
            <h1 class="page-title"><?= e(t('Archives')) ?></h1>
            <p class="lede">
                <?= e(t('Create, rename, recolour, nest and delete archives.')) ?>
            </p>
            <div class="page-actions">
                <button class="btn btn--primary" type="button" data-open-dialog="new-archive">
                    ＋ <?= e(t('New archive')) ?>
                </button>
            </div>
        </div>

        <ul class="row-list" data-sortable-archives data-endpoint="<?= e(url('/archives/reorder')) ?>">
            <?php foreach ($rows as $row): ?>
                <?php
                $item = $row['category'];
                $depth = (int) $row['depth'];
                $childCount = count($item['children']);
                ?>
                <li class="row-item<?= $depth > 0 ? ' row-item--child' : '' ?>"
                    data-sortable-item
                    data-id="<?= (int) $item['id'] ?>"
                    data-parent="<?= $item['parent_id'] === null ? '0' : (int) $item['parent_id'] ?>"
                    style="--depth: <?= $depth ?>">
                    <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>
                    <span class="chip-icon" style="font-size:17px; color: <?= e($item['color'] ?: 'inherit') ?>">
                        <?= e($item['icon'] ?: '•') ?>
                    </span>
                    <div class="row-main">
                        <div class="row-title">
                            <?= e($item['name']) ?>
                            <?php if ($childCount > 0): ?>
                                <span class="badge badge--muted">
                                    <?= e(tn($childCount, '%d sub-archive', '%d sub-archives')) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="row-sub">
                            <?= e(tn((int) $item['entry_count'], '%d entry', '%d entries')) ?>
                            <?php if (!empty($item['description'])): ?>
                                · <?= e($item['description']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="row-actions">
                        <a class="btn btn--sm btn--ghost" href="<?= e(url('/c/' . $item['slug'])) ?>"><?= e(t('Open')) ?></a>
                        <button class="btn btn--sm" type="button"
                                data-open-dialog="edit-archive-<?= (int) $item['id'] ?>"><?= e(t('Edit')) ?></button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($rows === []): ?>
            <div class="empty-state">
                <span class="empty-state-icon">🗂</span>
                <h3><?= e(t('No archives yet')) ?></h3>
                <p><?= e(t('Characters, species, magic systems, cities, artefacts — whatever your world needs.')) ?></p>
                <button class="btn btn--primary" type="button" data-open-dialog="new-archive">
                    <?= e(t('Create the first one')) ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<dialog id="new-archive">
    <form method="post" action="<?= e(url('/archives/create')) ?>">
        <?= csrf_field() ?>
        <div class="dialog-body">
            <h2 class="dialog-title"><?= e(t('New archive')) ?></h2>
            <p class="dialog-sub"><?= e(t('It starts with one simple layout, which you can reshape right away.')) ?></p>

            <div class="dialog-fields">
                <div>
                    <label for="new-archive-name"><?= e(t('Name')) ?></label>
                    <input class="input" id="new-archive-name" type="text" name="name" required
                           placeholder="<?= e(t('Factions')) ?>" autocomplete="off">
                </div>
                <div>
                    <label for="new-archive-parent"><?= e(t('Sits under')) ?></label>
                    <select class="select" id="new-archive-parent" name="parent_id">
                        <option value=""><?= e(t('Nothing — top level')) ?></option>
                        <?php foreach ($parents as $parent): ?>
                            <option value="<?= (int) $parent['id'] ?>">
                                <?= str_repeat('&nbsp;&nbsp;&nbsp;', (int) $parent['depth']) ?>
                                <?= e($parent['icon'] ?: '•') ?> <?= e($parent['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="inline-row">
                    <div>
                        <label><?= e(t('Icon')) ?></label>
                        <?php
                        $iconValue = '';
                        $iconId = 'new-archive';
                        include APP_ROOT . '/views/partials/_icon_field.php';
                        ?>
                    </div>
                    <div>
                        <label for="new-archive-color"><?= e(t('Colour')) ?></label>
                        <input id="new-archive-color" type="color" name="color"
                               value="<?= e($palette[count($categories) % count($palette)]) ?>">
                    </div>
                </div>
                <div>
                    <label for="new-archive-desc"><?= e(t('Description')) ?></label>
                    <input class="input" id="new-archive-desc" type="text" name="description"
                           placeholder="<?= e(t('One line about what belongs in here')) ?>">
                </div>
            </div>

            <div class="dialog-foot">
                <button class="btn btn--ghost" type="button" data-close-dialog><?= e(t('Cancel')) ?></button>
                <button class="btn btn--primary" type="submit"><?= e(t('Create archive')) ?></button>
            </div>
        </div>
    </form>
</dialog>

<?php foreach ($categories as $item): ?>
    <?php
    $itemId = (int) $item['id'];
    $hasChildren = $repo->hasChildren($itemId);
    // Excludes its own branch — can't be filed under itself or its descendants.
    $availableParents = $repo->possibleParents($itemId);
    ?>
    <dialog id="edit-archive-<?= $itemId ?>">
        <div class="dialog-body">
            <h2 class="dialog-title"><?= e(t('Edit “%s”', $item['name'])) ?></h2>
            <p class="dialog-sub"><?= e(t('Renaming changes its address; old links stop working.')) ?></p>

            <form method="post" action="<?= e(url('/archives/' . $itemId . '/update')) ?>">
                <?= csrf_field() ?>
                <div class="dialog-fields">
                    <div>
                        <label for="name-<?= $itemId ?>"><?= e(t('Name')) ?></label>
                        <input class="input" id="name-<?= $itemId ?>" type="text" name="name"
                               value="<?= e($item['name']) ?>" required>
                    </div>
                    <div>
                        <label for="parent-<?= $itemId ?>"><?= e(t('Sits under')) ?></label>
                            <select class="select" id="parent-<?= $itemId ?>" name="parent_id">
                                <option value=""><?= e(t('Nothing — top level')) ?></option>
                                <?php foreach ($availableParents as $parent): ?>
                                    <option value="<?= (int) $parent['id'] ?>"
                                        <?= (int) ($item['parent_id'] ?? 0) === (int) $parent['id'] ? 'selected' : '' ?>>
                                        <?= str_repeat('&nbsp;&nbsp;&nbsp;', (int) $parent['depth']) ?>
                                        <?= e($parent['icon'] ?: '•') ?> <?= e($parent['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($hasChildren): ?>
                                <p class="field-help">
                                    <?= e(t('Moving this takes its sub-archives with it.')) ?>
                                </p>
                            <?php endif; ?>
                    </div>
                    <div class="inline-row">
                        <div>
                            <label><?= e(t('Icon')) ?></label>
                            <?php
                            $iconValue = (string) ($item['icon'] ?? '');
                            $iconId = 'archive-' . $itemId;
                            include APP_ROOT . '/views/partials/_icon_field.php';
                            ?>
                        </div>
                        <div>
                            <label for="color-<?= $itemId ?>"><?= e(t('Colour')) ?></label>
                            <input id="color-<?= $itemId ?>" type="color" name="color"
                                   value="<?= e($item['color'] ?: '#8a8f98') ?>">
                        </div>
                    </div>
                    <div>
                        <label for="desc-<?= $itemId ?>"><?= e(t('Description')) ?></label>
                        <input class="input" id="desc-<?= $itemId ?>" type="text" name="description"
                               value="<?= e($item['description'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="sort-<?= $itemId ?>"><?= e(t('Opens sorted by')) ?></label>
                        <select class="select" id="sort-<?= $itemId ?>" name="default_sort">
                            <?php
                            $sorts = [
                                'title'    => t('A–Z'),
                                'timeline' => t('Chronological (by its number field)'),
                                'recent'   => t('Recently edited'),
                                'created'  => t('Newest first'),
                            ];
                            ?>
                            <?php foreach ($sorts as $value => $label): ?>
                                <option value="<?= e($value) ?>"
                                    <?= ($item['default_sort'] ?? 'title') === $value ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                            <?php $choices = ($sortFields ?? [])[$itemId] ?? []; ?>
                            <?php if ($choices !== []): ?>
                                <optgroup label="<?= e(t('By choice')) ?>">
                                    <?php foreach ($choices as $choice): ?>
                                        <option value="<?= e($choice['key']) ?>"
                                            <?= in_array(
                                                (int) substr((string) ($item['default_sort'] ?? ''), 6),
                                                $choice['ids'],
                                                true
                                            ) ? 'selected' : '' ?>>
                                            <?= e($choice['label']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="dialog-foot">
                    <button class="btn btn--ghost" type="button" data-close-dialog><?= e(t('Cancel')) ?></button>
                    <button class="btn btn--primary" type="submit"><?= e(t('Save')) ?></button>
                </div>
            </form>

            <div class="section" style="margin-top:26px">
                <h3 class="section-title"><?= e(t('Danger zone')) ?></h3>
                <form method="post" action="<?= e(url('/archives/' . $itemId . '/delete')) ?>">
                    <?= csrf_field() ?>
                    <p class="field-help">
                        <?= e(t('Deleting removes the archive, its layouts and all %s.',
                            tn((int) $item['entry_count'], '%d entry', '%d entries'))) ?>
                        <?php if ($hasChildren): ?>
                            <?php // %s is markup, not escaped — intentional. ?>
                            <?= t('Its sub-archives are <strong>not</strong> deleted — they move up to take its place.') ?>
                        <?php endif; ?>
                        <?php // %s is markup, not escaped — intentional. ?>
                        <?= t('Type %s to confirm.', '<strong>' . e($item['name']) . '</strong>') ?>
                    </p>
                    <div class="inline-row">
                        <input class="input" type="text" name="confirm_name"
                               placeholder="<?= e($item['name']) ?>" autocomplete="off">
                        <button class="btn btn--danger" type="submit" style="flex:0 0 auto">
                            <?= e(t('Delete archive')) ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </dialog>
<?php endforeach; ?>

<?php // One chooser for every icon field above. ?>
<?php include APP_ROOT . '/views/partials/icon_picker.php'; ?>
