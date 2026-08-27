<?php

/**
 * Month-grid calendar, drawn from the same Date/Era data as the linear timeline.
 *
 * @var int    $year
 * @var int    $month
 * @var int    $prevYear
 * @var int    $prevMonth
 * @var int    $nextYear
 * @var int    $nextMonth
 * @var ?int   $startWeekday weekday index (0-based) of this month's day 1
 * @var int    $monthDays
 * @var array  $holidays     day => names, for this month — see Calendar::holidaysForSurface()
 * @var array  $intercalaryHolidays day => names, one map per configured intercalary block
 * @var string $epochName
 * @var string $epochAbbr
 * @var array  $archives     every archive, for the column that filters the view
 */

$config = App\Calendar::config();
$monthName = $config['months'][$month - 1]['name'] ?? t('Month %d', $month);
$title = $monthName . ', ' . App\Timeline::formatYear($year);
?>
<div class="single-col">
    <div class="page page--wide">
        <div class="page-head">
            <div class="crumbs"><span><?= e(t('Calendar')) ?></span></div>
            <h1 class="page-title"><?= e(t('Calendar')) ?></h1>
            <p class="field-help">
                <?php // %s is markup, not escaped — intentional. ?>
                <?= t('Every Date and Era, laid out on your own calendar. <a href="%s">Design the calendar</a>.',
                    e(url('/settings/calendar'))) ?>
            </p>
        </div>

        <script type="application/json" data-calendar-config>
            <?= json_encode($config, JSON_UNESCAPED_UNICODE) ?>
        </script>
        <?php // Resolved server-side (Calendar::holidaysForSurface()) — the grid just looks up a day number. ?>
        <script type="application/json" data-calendar-holidays>
            <?= json_encode(['month' => $holidays, 'intercalary' => $intercalaryHolidays], JSON_UNESCAPED_UNICODE) ?>
        </script>

        <div class="timeline" data-calendar-grid
             data-base="<?= e(rtrim(url('/'), '/')) ?>"
             data-year="<?= (int) $year ?>"
             data-month="<?= (int) $month ?>"
             data-start-weekday="<?= $startWeekday === null ? '' : (int) $startWeekday ?>"
             data-month-days="<?= (int) $monthDays ?>"
             data-epoch-name="<?= e($epochName) ?>"
             data-epoch-abbr="<?= e($epochAbbr) ?>">

            <div class="timeline-bar">
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/timeline')) ?>">📈 <?= e(t('Timeline')) ?></a>
                <a class="btn btn--ghost btn--sm"
                   href="<?= e(url('/timeline/calendar?year=' . $prevYear . '&month=' . $prevMonth)) ?>">←</a>
                <h2 class="cal-title"><?= e($title) ?></h2>
                <a class="btn btn--ghost btn--sm"
                   href="<?= e(url('/timeline/calendar?year=' . $nextYear . '&month=' . $nextMonth)) ?>">→</a>

                <form class="cal-jump" method="get" action="<?= e(url('/timeline/calendar')) ?>">
                    <input class="input" type="text" inputmode="numeric" name="year"
                           value="<?= (int) $year ?>" style="width:90px" aria-label="<?= e(t('Year')) ?>">
                    <select class="select" name="month" style="width:auto" aria-label="<?= e(t('Month')) ?>">
                        <?php foreach ($config['months'] as $i => $m): ?>
                            <option value="<?= $i + 1 ?>" <?= $i + 1 === $month ? 'selected' : '' ?>>
                                <?= e($m['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn--ghost btn--sm" type="submit"><?= e(t('Go')) ?></button>
                </form>

                <span class="timeline-spacer"></span>
                <p class="timeline-note" data-cal-note hidden></p>
            </div>

            <div class="timeline-body">
                <?php
                // Which archives the calendar may show — the same switches,
                // and the same remembered choice, as the linear timeline.
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
                        <?= e(t('Switching an archive off takes its dots and bars off the calendar.')) ?>
                    </p>
                </aside>

                <div class="cal-stage" data-cal-stage>
                    <div class="cal-weekday-row" data-cal-weekday-row></div>
                    <div class="cal-weeks" data-cal-weeks></div>
                    <div class="cal-strip" data-cal-strip-before hidden></div>
                    <div class="cal-strip" data-cal-strip-after hidden></div>

                    <div class="timeline-blank" data-cal-blank hidden>
                        <span class="empty-state-icon">📅</span>
                        <h3><?= e(t('Nothing this month')) ?></h3>
                        <p><?= e(t('No entry dates or eras fall within it.')) ?></p>
                    </div>

                    <div class="worldmap-tip timeline-tip" data-cal-tip hidden></div>
                </div>
            </div>
        </div>
    </div>
</div>
