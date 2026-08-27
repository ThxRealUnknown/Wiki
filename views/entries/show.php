<?php
/**
 * One entry, rendered through the fields of its layout.
 *
 * @var array $category
 * @var array $entry
 * @var array $layout
 * @var array $fields
 * @var array $values
 * @var array $links
 * @var array $backlinks
 */
?>
<div class="columns">
    <?php include APP_ROOT . '/views/partials/entry_list.php'; ?>

    <div class="content-col">
        <?php
        // Banner field is pulled out and shown full width; the loop below skips it.
        $bannerFields = [];
        foreach ($fields as $candidate) {
            if (App\FieldTypes::isBanner((string) $candidate['field_type'])) {
                $bannerFields[(int) $candidate['id']] = $candidate;
            }
        }
        foreach ($bannerFields as $bannerId => $bannerField):
            $bannerPath = $values[$bannerId]['value_text'] ?? null;
            if ($bannerPath === null || $bannerPath === '') {
                continue;
            }
            ?>
            <div class="entry-banner">
                <img src="<?= e(url($bannerPath)) ?>" alt="<?= e($bannerField['label']) ?>">
            </div>
        <?php endforeach; ?>

        <div class="page<?= $bannerFields !== [] ? ' page--bannered' : '' ?>">
            <div class="page-head">
                <div class="crumbs">
                    <?= parent_crumb($category) ?><a href="<?= e(url('/c/' . $category['slug'])) ?>"><?= e($category['name']) ?></a> ›
                    <span><?= e($entry['title']) ?></span>
                </div>

                <h1 class="page-title"><?= e($entry['title']) ?></h1>

                <div class="page-meta">
                    <a class="badge badge--muted"
                       href="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'])) ?>"
                       title="<?= e(t('Edit this layout')) ?>">▤ <?= e($layout['name']) ?></a>
                    <?php if (($entry['archived_at'] ?? null) !== null): ?>
                        <span class="badge badge--muted" title="<?= e(t('Archived %s', human_time($entry['archived_at']))) ?>">
                            🗃 <?= e(t('Archived')) ?>
                        </span>
                    <?php endif; ?>
                    <span><?= e(t('Edited')) ?> <?= e(human_time($entry['updated_at'])) ?></span>
                    <span>· <?= e(t('Created %s', human_time($entry['created_at']))) ?></span>
                </div>

                <div class="page-actions">
                    <a class="btn btn--primary btn--sm"
                       href="<?= e(url('/c/' . $category['slug'] . '/e/' . $entry['slug'] . '/edit')) ?>">
                        ✎ <?= e(t('Edit')) ?>
                    </a>
                    <?php
                    // Wanted state is posted, not toggled, so label and action always agree.
                    $isFavorite = ($entry['favorited_at'] ?? null) !== null;
                    ?>
                    <form method="post"
                          action="<?= e(url('/c/' . $category['slug'] . '/e/' . $entry['slug'] . '/favorite')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="on" value="<?= $isFavorite ? '0' : '1' ?>">
                        <button class="btn btn--sm btn--star<?= $isFavorite ? ' is-on' : '' ?>"
                                type="submit"
                                title="<?= $isFavorite
                                    ? e(t('Remove from favourites'))
                                    : e(t('Keep this within reach on every page')) ?>">
                            <?= $isFavorite ? '★ ' . e(t('Favourited')) : '☆ ' . e(t('Favourite')) ?>
                        </button>
                    </form>
                    <button class="btn btn--sm" type="button" data-open-dialog="duplicate-modal"
                            title="<?= e(t('Copy or move this entry to another archive')) ?>">⧉ <?= e(t('Copy/Move')) ?></button>
                    <?php $isArchived = ($entry['archived_at'] ?? null) !== null; ?>
                    <form method="post"
                          action="<?= e(url('/c/' . $category['slug'] . '/e/' . $entry['slug'] . '/' . ($isArchived ? 'restore' : 'archive'))) ?>"
                          <?= $isArchived ? '' : 'data-confirm="' . e(t('Archive “%s”? It will stop appearing in other entries’ connections, search, and the pinboard until restored.', $entry['title'])) . '"' ?>>
                        <?= csrf_field() ?>
                        <button class="btn btn--sm" type="submit">
                            <?= $isArchived ? '↩ ' . e(t('Restore')) : '🗃 ' . e(t('Archive')) ?>
                        </button>
                    </form>
                    <form method="post"
                          action="<?= e(url('/c/' . $category['slug'] . '/e/' . $entry['slug'] . '/delete')) ?>"
                          data-confirm="<?= e(t('Delete “%s” for good?', $entry['title'])) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn--danger btn--sm" type="submit"><?= e(t('Delete')) ?></button>
                    </form>
                </div>
            </div>

            <dialog id="duplicate-modal">
                <form method="post"
                      action="<?= e(url('/c/' . $category['slug'] . '/e/' . $entry['slug'] . '/duplicate')) ?>">
                    <?= csrf_field() ?>

                    <div class="dialog-body">
                        <h2 class="dialog-title"><?= e(t('Copy or move')) ?></h2>
                        <p class="dialog-sub">
                            <?= e(t('Select an action and location.')) ?>
                        </p>

                        <div class="dialog-fields">
                            <div>
                                <label for="duplicate-mode"><?= e(t('Action')) ?></label>
                                <select class="select" id="duplicate-mode" name="mode">
                                    <option value="copy"><?= e(t('Copy')) ?></option>
                                    <option value="move"><?= e(t('Move')) ?></option>
                                </select>
                            </div>
                            <div>
                                <label for="duplicate-category"><?= e(t('To')) ?></label>
                                <select class="select" id="duplicate-category" name="category_id">
                                    <?php foreach ((new App\CategoryRepo())->flatTree() as $archive): ?>
                                        <option value="<?= (int) $archive['id'] ?>"
                                            <?= (int) $archive['id'] === (int) $category['id'] ? 'selected' : '' ?>>
                                            <?= str_repeat('&nbsp;&nbsp;&nbsp;', (int) $archive['depth']) ?>
                                            <?= e($archive['icon'] ?: '•') ?> <?= e($archive['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="dialog-foot">
                            <button class="btn btn--ghost" type="button" data-close-dialog><?= e(t('Cancel')) ?></button>
                            <button class="btn btn--primary" type="submit"><?= e(t('Continue')) ?></button>
                        </div>
                    </div>
                </form>
            </dialog>

            <?php if ($fields === []): ?>
                <div class="empty-state">
                    <span class="empty-state-icon">▤</span>
                    <h3><?= e(t('This layout has no fields')) ?></h3>
                    <p><?= e(t('Add some to “%s” and they will appear here.', $layout['name'])) ?></p>
                    <a class="btn"
                       href="<?= e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id'])) ?>">
                        <?= e(t('Edit layout')) ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="field-grid">
                    <?php foreach ($fields as $field): ?>
                        <?php if (isset($bannerFields[(int) $field['id']])) { continue; } ?>
                        <?php include APP_ROOT . '/views/entries/_field_display.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <?php
    $railType = 'entry';
    $railId = (int) $entry['id'];
    include APP_ROOT . '/views/partials/connections_rail.php';
    ?>
</div>
