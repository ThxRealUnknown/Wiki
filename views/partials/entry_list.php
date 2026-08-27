<?php

/**
 * The middle column: every entry in the current archive.
 *
 * @var array      $category
 * @var array      $entries
 * @var string     $search
 * @var string     $sort
 * @var array|null $activeEntry
 * @var array      $sortFields choice fields the layout offers as sort orders
 */

$search = $search ?? '';
$sort = $sort ?? 'title';
$sortFields = $sortFields ?? [];
// Which choice field the list is sorted by — one label can span a field
// across several layouts.
$sortFieldId = str_starts_with($sort, 'field:') ? (int) substr($sort, 6) : 0;
$activeEntry = $activeEntry ?? null;

// Views that do not paginate (the layout screens) still render this list.
$paging = $paging ?? [
    'total' => count($entries), 'page' => 1, 'pages' => 1,
    'per_page' => count($entries),
];

// Page one is left out of the query, to keep the common case's links clean.
$currentPage = max(1, (int) $paging['page']);
$statePage = $currentPage > 1 ? $currentPage : null;

// Opening an entry keeps the list's current order, filter and page; the
// pager overrides page on its own links.
$listState = static function (array $extra = []) use ($search, $sort, $statePage): string {
    $query = array_filter(
        array_merge(['q' => $search, 'sort' => $sort, 'page' => $statePage], $extra),
        static fn ($v) => $v !== null && $v !== ''
    );

    return $query === [] ? '' : '?' . http_build_query($query);
};
$isLayoutsView = ($layoutsViewActive ?? false);
$isArchivedView = ($archivedViewActive ?? false);
?>
<div class="list-col">
    <div class="list-head">
        <div class="list-title">
            <span class="archive-icon" style="--archive-color: <?= e($category['color'] ?: '#8a8f98') ?>">
                <?= e($category['icon'] ?: '•') ?>
            </span>
            <?= e($category['name']) ?>
        </div>
        <div class="list-sub">
            <?= e(tn((int) $paging['total'], '%d entry', '%d entries')) ?>
            <?php if ($search !== ''): ?> <?= e(t('matching “%s”', $search)) ?><?php endif; ?>
            <?php if ((int) $paging['pages'] > 1): ?>
                · <?= e(t('page %d of %d', (int) $paging['page'], (int) $paging['pages'])) ?>
            <?php endif; ?>
        </div>

        <div class="list-actions">
            <a class="btn btn--primary btn--sm" href="<?= e(url('/c/' . $category['slug'] . '/new')) ?>">
                ＋ <?= e(t('New entry')) ?>
            </a>
            <a class="btn btn--ghost btn--sm<?= $isLayoutsView ? ' is-active' : '' ?>"
               href="<?= e(url('/c/' . $category['slug'] . '/layouts')) ?>">
                ▤ <?= e(t('Layouts')) ?>
            </a>
            <a class="btn btn--ghost btn--sm<?= $isArchivedView ? ' is-active' : '' ?>"
               href="<?= e(url('/c/' . $category['slug'] . '/archived')) ?>">
                🗃 <?= e(t('Archived')) ?>
            </a>
        </div>

        <form class="list-filter" method="get" action="<?= e(url('/c/' . $category['slug'])) ?>">
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="<?= e(t('Filter this archive…')) ?>">
            <select name="sort" onchange="this.form.submit()">
                <option value="title"    <?= $sort === 'title' ? 'selected' : '' ?>><?= e(t('A–Z')) ?></option>
                <option value="timeline" <?= $sort === 'timeline' ? 'selected' : '' ?>><?= e(t('Chronological')) ?></option>
                <option value="recent"   <?= $sort === 'recent' ? 'selected' : '' ?>><?= e(t('Edited')) ?></option>
                <option value="created"  <?= $sort === 'created' ? 'selected' : '' ?>><?= e(t('Newest')) ?></option>
                <?php if ($sortFields !== []): ?>
                    <optgroup label="<?= e(t('By choice')) ?>">
                        <?php foreach ($sortFields as $sortField): ?>
                            <option value="<?= e($sortField['key']) ?>"
                                <?= in_array($sortFieldId, $sortField['ids'], true) ? 'selected' : '' ?>>
                                <?= e($sortField['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endif; ?>
            </select>
            <select name="per_page" onchange="this.form.submit()" title="<?= e(t('Entries per page')) ?>">
                <?php foreach (App\EntryRepo::PAGE_SIZES as $size): ?>
                    <option value="<?= $size ?>" <?= (int) $paging['per_page'] === $size ? 'selected' : '' ?>>
                        <?= $size ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if ($entries === []): ?>
        <div class="list-empty">
            <?php if ($search !== ''): ?>
                <?= e(t('Nothing matches that.')) ?>
            <?php else: ?>
                <?php // the translated value embeds its own <br> — not escaped, intentional. ?>
                <?= t('This archive is empty.<br>Start with the first entry.') ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="entry-list">
            <?php foreach ($entries as $item): ?>
                <?php $isActive = $activeEntry !== null && (int) $activeEntry['id'] === (int) $item['id']; ?>
                <li>
                    <a class="entry-item<?= $isActive ? ' is-active' : '' ?>"
                       href="<?= e(url('/c/' . $category['slug'] . '/e/' . $item['slug'] . $listState())) ?>">
                        <div class="entry-item-title"><?= e($item['title']) ?></div>
                        <div class="entry-item-meta">
                            <?= e($item['layout_name'] ?? '') ?>
                            <?php if (!empty($item['updated_at'])): ?>
                                · <?= e(human_time($item['updated_at'])) ?>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if ((int) $paging['pages'] > 1): ?>
            <?php
            $pageUrl = static fn (int $target): string => url(
                '/c/' . $category['slug'] . $listState(['page' => $target > 1 ? $target : null])
            );
            $current = (int) $paging['page'];
            $last = (int) $paging['pages'];
            ?>
            <nav class="pager">
                <a class="pager-step<?= $current <= 1 ? ' is-off' : '' ?>"
                   href="<?= e($pageUrl(max(1, $current - 1))) ?>"
                    <?= $current <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>←</a>

                <span class="pager-pos"><?= $current ?> / <?= $last ?></span>

                <a class="pager-step<?= $current >= $last ? ' is-off' : '' ?>"
                   href="<?= e($pageUrl(min($last, $current + 1))) ?>"
                    <?= $current >= $last ? 'aria-disabled="true" tabindex="-1"' : '' ?>>→</a>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</div>
