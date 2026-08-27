<?php

/**
 * The pinboard: one entry pinned up, and whatever the reader chooses to open
 * out from it. Nothing is drawn ahead of time — pins are fetched as they're opened.
 *
 * @var int   $startId    the entry to open on, or 0
 * @var string $startName
 * @var array $busiest    suggestions for a cold start
 * @var array $archives   every archive, for the column that filters the board
 */
?>
<div class="single-col">
    <div class="page page--wide">
        <div class="page-head">
            <div class="crumbs"><span><?= e(t('Pinboard')) ?></span></div>
            <h1 class="page-title"><?= e(t('Pinboard')) ?></h1>
            <p class="field-help">
                <?= e(t('Pin an entry, then click it to open out what it is tied to. Click again to fold it back in.')) ?>
            </p>
        </div>

        <?php // Trailing slash trimmed so the script can append its own paths. ?>
        <div class="pinboard" data-pinboard
             data-base="<?= e(rtrim(url('/'), '/')) ?>"
             data-start="<?= (int) $startId ?>">

            <div class="pinboard-bar">
                <div class="relation-field pinboard-find">
                    <input class="relation-search" type="text" autocomplete="off"
                           placeholder="<?= e(t('Pin an entry…')) ?>" aria-label="<?= e(t('Pin an entry')) ?>"
                           data-pinboard-search>
                    <ul class="relation-results" data-pinboard-results hidden></ul>
                </div>

                <span class="pinboard-spacer"></span>

                <?php // Both kinds of string are shown at once, and either can
                      // be taken out of the picture without losing the pins. ?>
                <label class="pinboard-toggle">
                    <input type="checkbox" checked data-pinboard-kind="connection">
                    <span class="pinboard-swatch pinboard-swatch--connection"></span>
                    <?= e(t('Connections')) ?>
                </label>
                <label class="pinboard-toggle">
                    <input type="checkbox" checked data-pinboard-kind="field">
                    <span class="pinboard-swatch pinboard-swatch--field"></span>
                    <?= e(t('Field links')) ?>
                </label>

                <button type="button" class="btn btn--ghost btn--sm" data-pinboard-fit><?= e(t('Fit')) ?></button>
                <button type="button" class="btn btn--ghost btn--sm" data-pinboard-clear><?= e(t('Clear')) ?></button>
            </div>

            <div class="pinboard-body">
            <?php
            // All archives render on by default — the script toggles off
            // whichever this reader had hidden, so new archives aren't missing.
            $renderFilter = static function (array $nodes, int $depth) use (&$renderFilter): string {
                $html = '';

                foreach ($nodes as $node) {
                    $html .= '<label class="pinboard-archive'
                        . ($depth > 0 ? ' pinboard-archive--child' : '') . '"'
                        . ' style="--archive-color: ' . e($node['color'] ?: 'var(--accent)') . '">'
                        . '<input type="checkbox" checked'
                        . ' data-pinboard-archive="' . (int) $node['id'] . '">'
                        . '<span class="archive-icon">' . e($node['icon'] ?: '•') . '</span>'
                        . '<span class="pinboard-archive-name">' . e($node['name']) . '</span>'
                        . '<span class="archive-count">' . (int) $node['entry_count'] . '</span>'
                        . '</label>';

                    $html .= $renderFilter($node['children'] ?? [], $depth + 1);
                }

                return $html;
            };
            ?>
            <aside class="pinboard-filter" data-pinboard-filter>
                <div class="pinboard-filter-head">
                    <button type="button" class="archive-toggle" data-filter-toggle
                            aria-expanded="true" aria-label="<?= e(t('Collapse Archives')) ?>">▾</button>
                    <h2 class="rail-title"><?= e(t('Archives')) ?></h2>
                    <button type="button" class="pinboard-filter-all" data-pinboard-archives-all>
                        <?= e(t('All')) ?>
                    </button>
                </div>

                <div class="pinboard-filter-list">
                    <?= $renderFilter($archives, 0) ?>
                </div>

                <p class="field-help pinboard-filter-note">
                    <?= e(t('Switching an archive off takes its entries off the board and stops them being brought in.')) ?>
                </p>
            </aside>

            <div class="pinboard-stage" data-pinboard-stage>
                <div class="pinboard-canvas" data-pinboard-canvas>
                    <svg class="pinboard-strings" data-pinboard-strings
                         width="6000" height="4500" viewBox="0 0 6000 4500"
                         aria-hidden="true"></svg>
                    <div class="pinboard-pins" data-pinboard-pins></div>
                </div>

                <div class="pinboard-blank" data-pinboard-blank <?= $startId > 0 ? 'hidden' : '' ?>>
                    <span class="empty-state-icon">⁂</span>
                    <h3><?= e(t('Nothing pinned yet')) ?></h3>
                    <p><?= e(t('Search above for anything in the archives, or start from one of these.')) ?></p>

                    <?php if ($busiest !== []): ?>
                        <div class="pinboard-suggestions">
                            <?php foreach ($busiest as $item): ?>
                                <button type="button" class="chip pinboard-suggestion"
                                        data-pinboard-pin="<?= (int) $item['id'] ?>"
                                        data-pinboard-suggestion-archive="<?= (int) $item['category'] ?>"
                                        style="--archive-color: <?= e($item['color'] ?: 'var(--accent)') ?>">
                                    <span class="chip-icon"><?= e($item['icon']) ?></span>
                                    <?= e($item['title']) ?>
                                    <span class="pinboard-degree"><?= (int) $item['degree'] ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <p class="pinboard-note" data-pinboard-note hidden></p>
            </div>
            </div>

            <?php // What a string is, once one has been clicked. ?>
            <div class="pinboard-inspect" data-pinboard-inspect hidden></div>

            <p class="field-help pinboard-legend">
                <?= e(t("Drag a pin to move it. Each pin has two rings on its right edge: the solid one ties a connection to any other pin, the dashed one points one of the entry's own fields at another entry — and only at the pins that field is allowed to reach. Hover a string to see what it is; click it to cut it. Chapters are not on the board — the rail on an entry still shows those.")) ?>
            </p>
        </div>
    </div>
</div>

<?php // Opened when a string is dragged between pins to add a note, and reused to edit an existing one. ?>
<dialog id="pinboard-connect-modal" data-pinboard-connect>
    <div class="dialog-body">
        <h2 class="dialog-title" data-pinboard-connect-title><?= e(t('Add connection')) ?></h2>

        <div class="dialog-fields">
            <div>
                <label><?= e(t('Connect to')) ?></label>
                <p class="connect-picked" data-pinboard-connect-ends></p>
            </div>
            <div>
                <label for="pinboard-connect-note"><?= e(t('Description (optional)')) ?></label>
                <input class="input" id="pinboard-connect-note" type="text" maxlength="300"
                       placeholder="<?= e(t('What is this connection?')) ?>" data-pinboard-connect-note>
            </div>
        </div>

        <div class="dialog-foot">
            <button class="btn btn--ghost" type="button" data-close-dialog><?= e(t('Cancel')) ?></button>
            <button class="btn btn--primary" type="button" data-pinboard-connect-add><?= e(t('Add')) ?></button>
        </div>
    </div>
</dialog>
