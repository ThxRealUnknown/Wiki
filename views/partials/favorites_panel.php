<?php

/**
 * The favourites panel: entries pinned for quick reach, on every page. Folds
 * out over the rail rather than reflowing the page; open/closed state lives
 * in the browser, not the database.
 */

$favorites = (new App\EntryRepo())->favorites();

// Archived favourites stay pinned, but kept separate from the rest.
$archivedFavorites = [];
$activeFavorites = [];
foreach ($favorites as $favorite) {
    if (($favorite['archived_at'] ?? null) !== null) {
        $archivedFavorites[] = $favorite;
    } else {
        $activeFavorites[] = $favorite;
    }
}

// Grouped by archive, in the order EntryRepo already sorted them into.
$groups = [];
foreach ($activeFavorites as $favorite) {
    $groups[(int) $favorite['category_id']]['category'] = [
        'name'  => $favorite['category_name'],
        'icon'  => $favorite['category_icon'] ?: '•',
        'slug'  => $favorite['category_slug'],
        'color' => $favorite['category_color'] ?: '',
    ];
    $groups[(int) $favorite['category_id']]['items'][] = $favorite;
}

// Where the unpin buttons should send the browser back to.
$favoritesReturn = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
?>
<aside class="favorites" data-favorites>
    <button type="button" class="favorites-handle" data-favorites-toggle
            aria-expanded="false" aria-controls="favorites-panel">
        <span class="favorites-handle-star">★</span>
        <span class="favorites-handle-label"><?= e(t('Favourites')) ?></span>
        <?php if ($favorites !== []): ?>
            <span class="favorites-handle-count"><?= count($favorites) ?></span>
        <?php endif; ?>
    </button>

    <div class="favorites-panel" id="favorites-panel">
        <div class="favorites-head">
            <h2 class="rail-title"><?= e(t('Favourites')) ?></h2>
            <span class="rail-count"><?= count($favorites) ?></span>
            <button type="button" class="favorites-close" data-favorites-toggle
                    aria-label="<?= e(t('Close favourites')) ?>">✕</button>
        </div>

        <?php if ($favorites === []): ?>
            <p class="rail-empty">
                <?= e(t('Nothing pinned yet. The ★ on any entry puts it here, and it stays within reach on every page.')) ?>
            </p>
        <?php endif; ?>

        <div class="favorites-body">
            <?php foreach ($groups as $group): ?>
                <div class="rail-group">
                    <h3 class="rail-group-title">
                        <a href="<?= e(url('/c/' . $group['category']['slug'])) ?>">
                            <?php // The archive's own colour, as in the sidebar. ?>
                            <span class="archive-icon"
                                  style="--archive-color: <?= e($group['category']['color'] ?: 'var(--text-muted)') ?>">
                                <?= e($group['category']['icon']) ?>
                            </span>
                            <?= e($group['category']['name']) ?>
                        </a>
                    </h3>
                    <ul class="rail-list">
                        <?php foreach ($group['items'] as $item): ?>
                            <li class="rail-item">
                                <a class="rail-link"
                                   href="<?= e(url('/c/' . $item['category_slug'] . '/e/' . $item['slug'])) ?>">
                                    <span class="rail-item-title"><?= e($item['title']) ?></span>
                                </a>
                                <form method="post"
                                      action="<?= e(url('/c/' . $item['category_slug'] . '/e/' . $item['slug'] . '/favorite')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="on" value="0">
                                    <input type="hidden" name="return_to" value="<?= e($favoritesReturn) ?>">
                                    <button class="rail-remove" type="submit"
                                            title="<?= e(t('Remove from favourites')) ?>">★</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>

            <?php if ($archivedFavorites !== []): ?>
                <div class="rail-group">
                    <h3 class="rail-group-title"><?= e(t('Archived')) ?></h3>
                    <ul class="rail-list">
                        <?php foreach ($archivedFavorites as $item): ?>
                            <li class="rail-item">
                                <a class="rail-link"
                                   href="<?= e(url('/c/' . $item['category_slug'] . '/e/' . $item['slug'])) ?>">
                                    <span class="chip-icon"><?= e($item['category_icon'] ?: '•') ?></span>
                                    <span class="rail-item-title"><?= e($item['title']) ?></span>
                                </a>
                                <form method="post"
                                      action="<?= e(url('/c/' . $item['category_slug'] . '/e/' . $item['slug'] . '/favorite')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="on" value="0">
                                    <input type="hidden" name="return_to" value="<?= e($favoritesReturn) ?>">
                                    <button class="rail-remove" type="submit"
                                            title="<?= e(t('Remove from favourites')) ?>">★</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</aside>
