<?php

use App\FieldTypes;
use App\Settings;

/**
 * The right-hand rail: everything this page is connected to, from any direction.
 *
 * @var string     $railType   'entry' or 'chapter'
 * @var int        $railId
 * @var array      $connections free-form connections, from ConnectionRepo
 * @var array|null $fields      layout fields, when the page is an entry
 * @var array|null $links       relation-field targets keyed by field id
 * @var array|null $backlinks   entries pointing here through a relation field
 */

// Connections off: rail keeps only automatic backlinks from relation fields.
$connectionsEnabled = Settings::flag(Settings::FEATURE_CONNECTIONS);

$fields = $fields ?? [];
$links = $links ?? [];
$backlinks = $backlinks ?? [];
$connections = $connections ?? [];

// The Story is a reader's view, so its rail shows connections without offering
// any way to change them.
$readOnly = $railReadOnly ?? false;

// Where the add/remove forms should send the browser back to.
$returnTo = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

// Field relations, flattened into "which field, pointing where".
$fieldLinks = [];
foreach ($fields as $field) {
    if (!FieldTypes::isRelation((string) $field['field_type'])) {
        continue;
    }
    foreach ($links[(int) $field['id']] ?? [] as $target) {
        $fieldLinks[] = ['field' => $field['label']] + $target;
    }
}

$grouped = [];
foreach ($connections as $connection) {
    $grouped[$connection['context']][] = $connection;
}

$total = $connectionsEnabled
    ? count($connections) + count($fieldLinks) + count($backlinks)
    : count($backlinks);
?>
<aside class="rail">
    <div class="worldmap-tip rail-tip" data-rail-tip hidden></div>

    <div class="rail-head">
        <h2 class="rail-title"><?= e(t('Connections')) ?></h2>
        <span class="rail-count"><?= $total ?></span>
        <?php // Entries only — the pinboard has no pin for a chapter. ?>
        <?php if ($connectionsEnabled && $railType === 'entry'): ?>
            <a class="rail-board" href="<?= e(url('/pinboard?entry=' . (int) $railId)) ?>"
               title="<?= e(t('Open this on the pinboard')) ?>">⁂</a>
        <?php endif; ?>
        <?php if ($connectionsEnabled && !$readOnly): ?>
            <button type="button" class="rail-board rail-add-trigger"
                    data-open-dialog="connect-modal" title="<?= e(t('Add a connection')) ?>">+</button>
        <?php endif; ?>
    </div>

    <?php
    // Ids already connected, so the picker can leave them out of its list.
    $connectedEntries = [];
    $connectedChapters = [];
    foreach ($connections as $connection) {
        if ($connection['type'] === 'chapter') {
            $connectedChapters[] = (int) $connection['id'];
        } else {
            $connectedEntries[] = (int) $connection['id'];
        }
    }
    ?>

    <?php if ($total === 0): ?>
        <p class="rail-empty">
            <?php if (!$connectionsEnabled): ?>
                <?= e(t('Nothing references this yet.')) ?>
            <?php elseif ($readOnly): ?>
                <?= e(t('Nothing is connected to this chapter.')) ?>
            <?php else: ?>
                <?= e(t('Nothing connected yet. Use the + above to tie this to any entry — or to a chapter.')) ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <?php if ($connectionsEnabled): ?>
        <?php foreach ($grouped as $context => $items): ?>
            <div class="rail-group">
                <h3 class="rail-group-title"><?= e($context) ?></h3>
                <ul class="rail-list">
                    <?php foreach ($items as $item): ?>
                        <li class="rail-item">
                            <a class="rail-link" href="<?= e($item['url']) ?>"
                               <?php if (!empty($item['note'])): ?>
                               data-rail-note="<?= e($item['note']) ?>"
                               <?php endif; ?>>
                                <span class="chip-icon"><?= e($item['icon']) ?></span>
                                <span class="rail-item-title"><?= e($item['title']) ?></span>
                            </a>
                            <?php if (!$readOnly): ?>
                                <button type="button" class="rail-edit" data-rail-edit
                                        data-connection-id="<?= (int) $item['connection_id'] ?>"
                                        data-connection-note="<?= e($item['note'] ?? '') ?>"
                                        data-connection-title="<?= e($item['title']) ?>"
                                        title="<?= e(t('Edit this connection')) ?>">✎</button>
                                <form method="post"
                                      action="<?= e(url('/connections/' . $item['connection_id'] . '/delete')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                                    <button class="rail-remove" type="submit"
                                            title="<?= e(t('Remove this connection')) ?>">×</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>

        <?php if ($fieldLinks !== []): ?>
            <div class="rail-group">
                <h3 class="rail-group-title"><?= e(t('Linked in fields')) ?></h3>
                <ul class="rail-list">
                    <?php foreach ($fieldLinks as $item): ?>
                        <li class="rail-item">
                            <a class="rail-link"
                               href="<?= e(url('/c/' . $item['category_slug'] . '/e/' . $item['slug'])) ?>">
                                <span class="chip-icon"><?= e($item['category_icon'] ?: '•') ?></span>
                                <span class="rail-item-title"><?= e($item['title']) ?></span>
                                <span class="rail-item-where"><?= e($item['field']) ?><?= !empty($item['relation_type']) ? ' · ' . e($item['relation_type']) : '' ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($backlinks !== []): ?>
        <div class="rail-group">
            <h3 class="rail-group-title"><?= e(t('Referenced by')) ?></h3>
            <ul class="rail-list">
                <?php foreach ($backlinks as $item): ?>
                    <li class="rail-item">
                        <a class="rail-link"
                           href="<?= e(url('/c/' . $item['category_slug'] . '/e/' . $item['slug'])) ?>">
                            <span class="chip-icon"><?= e($item['category_icon'] ?: '•') ?></span>
                            <span class="rail-item-title"><?= e($item['title']) ?></span>
                            <span class="rail-item-where"><?= e($item['field_label']) ?><?= !empty($item['relation_type']) ? ' · ' . e($item['relation_type']) : '' ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</aside>

<?php if ($connectionsEnabled && !$readOnly): ?>
<dialog id="connect-modal" class="connect-dialog" data-connect-picker
        data-endpoint="<?= e(url('/api/lookup')) ?>"
        data-exclude="<?= (int) $railId ?>"
        data-exclude-type="<?= e($railType) ?>"
        data-connected-entries="<?= e(implode(',', $connectedEntries)) ?>"
        data-connected-chapters="<?= e(implode(',', $connectedChapters)) ?>">
    <form method="post" action="<?= e(url('/connections/create')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="from_type" value="<?= e($railType) ?>">
        <input type="hidden" name="from_id" value="<?= (int) $railId ?>">
        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
        <input type="hidden" name="to_type" value="" data-connect-to-type>
        <input type="hidden" name="to_id" value="" data-connect-to-id>

        <div class="dialog-body">
            <h2 class="dialog-title"><?= e(t('Add connection')) ?></h2>
            <p class="dialog-sub"><?= e(t('Tie this to any entry, or to a chapter.')) ?></p>

            <div class="dialog-fields">
                <div class="relation-field">
                    <label for="connect-search"><?= e(t('Connect to')) ?></label>
                    <input class="relation-search" id="connect-search" type="text" autocomplete="off"
                           placeholder="<?= e(t('Search entries and chapters…')) ?>" data-connect-search>
                    <ul class="relation-results" data-connect-results hidden></ul>

                    <div class="connect-picked" data-connect-picked hidden>
                        <span class="chip-icon" data-connect-picked-icon></span>
                        <span class="connect-picked-title" data-connect-picked-title></span>
                        <button type="button" class="connect-picked-clear" data-connect-picked-clear
                                title="<?= e(t('Choose a different entry')) ?>">&times;</button>
                    </div>
                </div>
                <div>
                    <label for="connect-note"><?= e(t('Description (optional)')) ?></label>
                    <input class="input" id="connect-note" type="text" name="note" maxlength="300"
                           placeholder="<?= e(t('What is this connection?')) ?>">
                </div>
            </div>

            <div class="dialog-foot">
                <button class="btn btn--ghost" type="button" data-close-dialog><?= e(t('Cancel')) ?></button>
                <button class="btn btn--primary" type="submit" data-connect-submit disabled><?= e(t('Add')) ?></button>
            </div>
        </div>
    </form>
</dialog>

<dialog id="edit-connection-modal" data-edit-connection>
    <form method="post" data-edit-connection-form>
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">

        <div class="dialog-body">
            <h2 class="dialog-title"><?= e(t('Edit connection')) ?></h2>
            <p class="dialog-sub" data-edit-connection-title></p>

            <div class="dialog-fields">
                <div>
                    <label for="edit-connection-note"><?= e(t('Description (optional)')) ?></label>
                    <input class="input" id="edit-connection-note" type="text" name="note" maxlength="300"
                           placeholder="<?= e(t('What is this connection?')) ?>">
                </div>
            </div>

            <div class="dialog-foot">
                <button class="btn btn--ghost" type="button" data-close-dialog><?= e(t('Cancel')) ?></button>
                <button class="btn btn--primary" type="submit"><?= e(t('Save')) ?></button>
            </div>
        </div>
    </form>
</dialog>
<?php endif; ?>
