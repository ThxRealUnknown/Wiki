<?php

/**
 * The timeline: every Date and Era field value, fetched once from /timeline/events.
 *
 * @var int    $focus     an entry to centre on and highlight, or 0
 * @var string $epochName e.g. "After the Fracture"
 * @var string $epochAbbr e.g. "A.F."
 * @var array  $archives  every archive, for the column that filters the timeline
 */
?>
<div class="single-col">
    <div class="page page--wide">
        <div class="page-head">
            <div class="crumbs"><span><?= e(t('Timeline')) ?></span></div>
            <h1 class="page-title"><?= e(t('Timeline')) ?></h1>
            <p class="field-help">
                <?= e(t('Drag to pan, scroll to zoom.')) ?>
                <?php if ($epochName !== ''): ?>
                    <?= e(t('Years are counted in %s', $epochName)) ?>
                    <?php if ($epochAbbr !== ''): ?> (<?= e($epochAbbr) ?>)<?php endif; ?>.
                <?php endif; ?>
            </p>
        </div>

        <?php // Trailing slash trimmed so the script can append its own paths. ?>
        <div class="timeline" data-timeline
             data-base="<?= e(rtrim(url('/'), '/')) ?>"
             data-focus="<?= (int) $focus ?>"
             data-epoch-name="<?= e($epochName) ?>"
             data-epoch-abbr="<?= e($epochAbbr) ?>">

            <div class="timeline-bar">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/timeline/calendar')) ?>">📅 <?= e(t('Calendar')) ?></a>
                <button type="button" class="btn btn--ghost btn--sm" data-timeline-zoom-out>−</button>
                <button type="button" class="btn btn--ghost btn--sm" data-timeline-zoom-in>+</button>
                <button type="button" class="btn btn--ghost btn--sm" data-timeline-fit><?= e(t('Fit')) ?></button>
                <span class="timeline-spacer"></span>
                <p class="timeline-note" data-timeline-note hidden></p>
            </div>

            <div class="timeline-body">
            <?php
            // Which archives the timeline may show.
            $renderFilter = static function (array $nodes, int $depth) use (&$renderFilter): string {
                $html = '';

                foreach ($nodes as $node) {
                    $html .= '<label class="pinboard-archive'
                        . ($depth > 0 ? ' pinboard-archive--child' : '') . '"'
                        . ' style="--archive-color: ' . e($node['color'] ?: 'var(--accent)') . '">'
                        . '<input type="checkbox" checked'
                        . ' data-timeline-archive="' . (int) $node['id'] . '">'
                        . '<span class="archive-icon">' . e($node['icon'] ?: '•') . '</span>'
                        . '<span class="pinboard-archive-name">' . e($node['name']) . '</span>'
                        . '<span class="archive-count">' . (int) $node['entry_count'] . '</span>'
                        . '</label>';

                    $html .= $renderFilter($node['children'] ?? [], $depth + 1);
                }

                return $html;
            };
            ?>
            <aside class="pinboard-filter" data-timeline-filter>
                <div class="pinboard-filter-head">
                    <button type="button" class="archive-toggle" data-filter-toggle
                            aria-expanded="true" aria-label="<?= e(t('Collapse Archives')) ?>">▾</button>
                    <h2 class="rail-title"><?= e(t('Archives')) ?></h2>
                    <button type="button" class="pinboard-filter-all" data-timeline-archives-all>
                        <?= e(t('All')) ?>
                    </button>
                </div>

                <div class="pinboard-filter-list">
                    <?= $renderFilter($archives, 0) ?>
                </div>

                <p class="field-help pinboard-filter-note">
                    <?= e(t('Switching an archive off takes its points and spans off the timeline.')) ?>
                </p>
            </aside>

            <div class="timeline-stage" data-timeline-stage>
                <div class="timeline-canvas" data-timeline-canvas>
                    <div class="timeline-axis" data-timeline-axis></div>
                    <div class="timeline-eras" data-timeline-eras></div>
                    <div class="timeline-points" data-timeline-points></div>
                </div>

                <div class="timeline-blank" data-timeline-blank hidden>
                    <span class="empty-state-icon">⏳</span>
                    <h3><?= e(t('Nothing on the timeline yet')) ?></h3>
                    <p>
                        <?= e(t('Add a Date or Era field to a layout, then give an entry a year — it will show up here.')) ?>
                    </p>
                </div>

                <div class="worldmap-tip timeline-tip" data-timeline-tip hidden></div>
            </div>
            </div>
        </div>
    </div>
</div>
