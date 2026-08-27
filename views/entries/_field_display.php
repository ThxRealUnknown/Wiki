<?php

use App\FieldTypes;
use App\Settings;

/**
 * Renders one field of an entry, read-only.
 *
 * @var array $field  the layout field definition
 * @var array $values entry values keyed by field id
 * @var array $links  relation targets keyed by field id
 */

$fieldId = (int) $field['id'];
$type = (string) $field['field_type'];

// Map disabled: the field is skipped entirely rather than shown empty.
if (in_array($type, [FieldTypes::MAPAREA, FieldTypes::MAPPOINT, FieldTypes::MAPPATH], true)
    && !Settings::flag(Settings::FEATURE_MAP)) {
    return;
}

$config = $field['config'] ?? [];
$stored = $values[$fieldId]['value_text'] ?? null;
$targets = $links[$fieldId] ?? [];

$isEmpty = match (true) {
    FieldTypes::isRelation($type) => $targets === [],
    default                       => $stored === null || $stored === '',
};
?>
<div class="field-block<?= ($field['width'] ?? 'full') === 'half' ? ' field-block--half' : '' ?>">
    <span class="field-label"><?= e($field['label']) ?></span>

    <?php if ($isEmpty): ?>
        <div class="field-value field-value--empty"><?= e(t('Not set')) ?></div>

    <?php elseif ($type === FieldTypes::MAPAREA):
        $area = App\WorldMap::parseArea($stored);
        ?>
        <?php if ($area === null): ?>
            <div class="field-value field-value--empty"><?= e(t('Not set')) ?></div>
        <?php else:
            $layer = App\WorldMap::layer($area['layer']);
            // Must be unique per field, or cutouts on the same page would share a mask.
            $maskId = 'cut-' . $fieldId;

            // Filtered client-side with isPointInFill(), which handles curved borders.
            static $pointCache = null;
            if ($pointCache === null) {
                $pointCache = (new App\MapRepo())->pointsByLayer();
            }
            $nearby = $pointCache[$area['layer']] ?? [];
            ?>
            <div class="mapcut-wrap">
                <a class="mapcut" href="<?= e(url('/map?layer=' . $area['layer'] . '&focus=' . (int) ($entry['id'] ?? 0))) ?>">
                    <svg class="mapcut-svg" data-mapcut
                         data-path="<?= e($area['d']) ?>"
                         viewBox="0 0 <?= App\WorldMap::WIDTH ?> <?= App\WorldMap::HEIGHT ?>"
                         preserveAspectRatio="xMidYMid meet" role="img"
                         aria-label="<?= e(t('%s on the %s map', $field['label'], $layer['label'] ?? t('world'))) ?>">
                        <?php
                        // viewBox no longer starts at 0,0 after cropping, so 100% sizing
                        // would miss — this overshoots any possible crop instead.
                        $span = 'x="' . -App\WorldMap::WIDTH . '" y="' . -App\WorldMap::HEIGHT . '"'
                            . ' width="' . App\WorldMap::WIDTH * 3 . '"'
                            . ' height="' . App\WorldMap::HEIGHT * 3 . '"';
                        ?>
                        <defs>
                            <mask id="<?= e($maskId) ?>">
                                <?php // White shows the map through; black dims it. ?>
                                <rect <?= $span ?> fill="#fff"/>
                                <path d="<?= e($area['d']) ?>" fill="#000"/>
                            </mask>
                        </defs>

                        <?php if (!empty($layer['image'])): ?>
                            <image href="<?= e(asset($layer['image'])) ?>" x="0" y="0"
                                   width="<?= App\WorldMap::WIDTH ?>" height="<?= App\WorldMap::HEIGHT ?>"
                                   preserveAspectRatio="none"/>
                        <?php else: ?>
                            <rect <?= $span ?> class="mapcut-blank"/>
                        <?php endif; ?>

                        <?php // Everything outside the shape goes quiet. ?>
                        <rect <?= $span ?> class="mapcut-dim"
                              mask="url(#<?= e($maskId) ?>)"/>
                        <?php // Uses the world map's hover card, not a native tooltip. ?>
                        <path d="<?= e($area['d']) ?>" class="mapcut-shape"
                              data-tip-title="<?= e($entry['title'] ?? $field['label']) ?>"
                              data-tip-sub="<?= e($category['name'] ?? ($layer['label'] ?? '')) ?>"
                              data-tip-icon="<?= e($category['icon'] ?? '') ?>"/>
                    </svg>
                    <span class="mapcut-caption"><?= e($layer['label'] ?? t('World map')) ?></span>
                </a>

                <?php if ($nearby !== []): ?>
                    <?php // Separate SVG so points are their own links, not nested in the map's. ?>
                    <svg class="mapcut-points" data-mapcut-points
                         viewBox="0 0 <?= App\WorldMap::WIDTH ?> <?= App\WorldMap::HEIGHT ?>"
                         preserveAspectRatio="xMidYMid meet" aria-hidden="false">
                        <?php foreach ($nearby as $point): ?>
                            <a href="<?= e(url($point['url'])) ?>" class="mapcut-point"
                               data-x="<?= (float) $point['x'] ?>" data-y="<?= (float) $point['y'] ?>"
                               data-tip-title="<?= e($point['title']) ?>"
                               data-tip-sub="<?= e($point['archive']) ?>"
                               data-tip-icon="<?= e($point['icon'] ?: '') ?>"
                               aria-label="<?= e($point['title']) ?> — <?= e($point['archive']) ?>"
                               style="--region-color: <?= e($point['color'] ?: 'var(--accent)') ?>">
                                <text x="<?= (float) $point['x'] ?>" y="<?= (float) $point['y'] ?>"
                                      class="mapcut-point-glyph"><?= e($point['glyph']) ?></text>
                                <?php // The glyph's hit area is tiny at this scale — this circle is what the pointer meets. ?>
                                <circle cx="<?= (float) $point['x'] ?>" cy="<?= (float) $point['y'] ?>"
                                        r="46" class="mapcut-point-hit"/>
                            </a>
                        <?php endforeach; ?>
                    </svg>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($type === FieldTypes::MAPPOINT):
        $point = App\WorldMap::parsePoint($stored);
        ?>
        <?php if ($point === null): ?>
            <div class="field-value field-value--empty"><?= e(t('Not set')) ?></div>
        <?php else:
            $layer = App\WorldMap::layer($point['layer']);
            // A point has no extent of its own, so the cutout shows a fixed
            // window of the world around it.
            $half = App\WorldMap::POINT_WINDOW / 2;
            $vx = max(0, min(App\WorldMap::WIDTH - App\WorldMap::POINT_WINDOW, $point['x'] - $half));
            $vy = max(0, min(App\WorldMap::HEIGHT - App\WorldMap::POINT_WINDOW, $point['y'] - $half));
            ?>
            <div class="mapcut-wrap">
            <a class="mapcut" href="<?= e(url('/map?layer=' . $point['layer'] . '&focus=' . (int) ($entry['id'] ?? 0))) ?>">
                <svg class="mapcut-svg"
                     viewBox="<?= (int) $vx ?> <?= (int) $vy ?> <?= App\WorldMap::POINT_WINDOW ?> <?= App\WorldMap::POINT_WINDOW ?>"
                     preserveAspectRatio="xMidYMid slice" role="img"
                     aria-label="<?= e($field['label']) ?> on the <?= e($layer['label'] ?? 'world') ?> map">
                    <?php if (!empty($layer['image'])): ?>
                        <image href="<?= e(asset($layer['image'])) ?>" x="0" y="0"
                               width="<?= App\WorldMap::WIDTH ?>" height="<?= App\WorldMap::HEIGHT ?>"
                               preserveAspectRatio="none"/>
                    <?php else: ?>
                        <rect width="100%" height="100%" class="mapcut-blank"/>
                    <?php endif; ?>
                    <g class="mapcut-here-mark"
                       data-tip-title="<?= e($entry['title'] ?? $field['label']) ?>"
                       data-tip-sub="<?= e($category['name'] ?? ($layer['label'] ?? '')) ?>"
                       data-tip-icon="<?= e($category['icon'] ?? '') ?>">
                        <text x="<?= (float) $point['x'] ?>" y="<?= (float) $point['y'] ?>"
                              class="mapcut-here-glyph"><?= e(App\WorldMap::glyph($point['symbol'] ?? null)) ?></text>
                        <circle cx="<?= (float) $point['x'] ?>" cy="<?= (float) $point['y'] ?>"
                                r="46" class="mapcut-point-hit"/>
                    </g>
                </svg>
                <span class="mapcut-caption"><?= e($layer['label'] ?? t('World map')) ?></span>
            </a>
            </div>
        <?php endif; ?>

    <?php elseif ($type === FieldTypes::MAPPATH):
        $mapPath = App\WorldMap::parsePath($stored);
        ?>
        <?php if ($mapPath === null): ?>
            <div class="field-value field-value--empty"><?= e(t('Not set')) ?></div>
        <?php else:
            $layer = App\WorldMap::layer($mapPath['layer']);
            ?>
            <div class="mapcut-wrap">
                <a class="mapcut" href="<?= e(url('/map?layer=' . $mapPath['layer'] . '&focus=' . (int) ($entry['id'] ?? 0))) ?>">
                    <svg class="mapcut-svg" data-mapcut
                         data-path="<?= e($mapPath['d']) ?>"
                         viewBox="0 0 <?= App\WorldMap::WIDTH ?> <?= App\WorldMap::HEIGHT ?>"
                         preserveAspectRatio="xMidYMid meet" role="img"
                         aria-label="<?= e(t('%s on the %s map', $field['label'], $layer['label'] ?? t('world'))) ?>">
                        <?php if (!empty($layer['image'])): ?>
                            <image href="<?= e(asset($layer['image'])) ?>" x="0" y="0"
                                   width="<?= App\WorldMap::WIDTH ?>" height="<?= App\WorldMap::HEIGHT ?>"
                                   preserveAspectRatio="none"/>
                        <?php else: ?>
                            <rect width="100%" height="100%" class="mapcut-blank"/>
                        <?php endif; ?>
                        <?php // No dimming mask — a route has nothing to enclose, just the line. ?>
                        <path d="<?= e($mapPath['d']) ?>" class="mapcut-shape mapcut-shape--line"
                              data-tip-title="<?= e($entry['title'] ?? $field['label']) ?>"
                              data-tip-sub="<?= e($category['name'] ?? ($layer['label'] ?? '')) ?>"
                              data-tip-icon="<?= e($category['icon'] ?? '') ?>"/>
                    </svg>
                    <span class="mapcut-caption"><?= e($layer['label'] ?? t('World map')) ?></span>
                </a>
            </div>
        <?php endif; ?>

    <?php elseif ($type === FieldTypes::RICHTEXT): ?>
        <?php // Sanitised on save; links are stored by guid so renaming the target won't break them. ?>
        <div class="field-value prose"><?= App\EntryLinks::resolve($stored) ?></div>

    <?php elseif ($type === FieldTypes::TEXTAREA): ?>
        <div class="field-value field-value--plain"><?= e($stored) ?></div>

    <?php elseif (FieldTypes::isUpload($type)): ?>
        <img class="field-image<?= ($field['width'] ?? 'full') === 'full' ? ' field-image--hero' : '' ?>"
             src="<?= e(url($stored)) ?>" alt="<?= e($field['label']) ?>" loading="lazy">

    <?php elseif ($type === FieldTypes::TAGS): ?>
        <?php $tags = json_decode((string) $stored, true) ?: []; ?>
        <div class="chip-row">
            <?php foreach ($tags as $tag): ?>
                <a class="chip chip--link" href="<?= e(url('/tags/' . rawurlencode((string) $tag))) ?>">
                    <?= e((string) $tag) ?>
                </a>
            <?php endforeach; ?>
        </div>

    <?php elseif ($type === FieldTypes::SELECT): ?>
        <div class="field-value"><span class="badge badge--muted"><?= e($stored) ?></span></div>

    <?php elseif ($type === FieldTypes::NUMBER): ?>
        <div class="field-value">
            <?= e($stored) ?><?php if (!empty($config['unit'])): ?>
                <span class="field-help" style="display:inline"><?= e($config['unit']) ?></span>
            <?php endif; ?>
        </div>

    <?php elseif ($type === FieldTypes::DATE):
        // Pre-calendar data may not decode — show it plainly rather than fail.
        $date = App\Calendar::decode($stored);
        ?>
        <?php if ($date === null): ?>
            <div class="field-value field-value--plain"><?= e($stored) ?></div>
        <?php else: ?>
            <div class="field-value">
                <a class="chip chip--link" href="<?= e(url('/timeline?focus=' . (int) ($entry['id'] ?? 0))) ?>">
                    <?= e(App\Calendar::formatDate($date)) ?>
                </a>
            </div>
        <?php endif; ?>

    <?php elseif (FieldTypes::isEra($type)):
        $era = App\Calendar::decodeEra($stored);
        ?>
        <?php if ($era === null || $era['from'] === null || $era['to'] === null): ?>
            <div class="field-value field-value--empty"><?= e(t('Not set')) ?></div>
        <?php else: ?>
            <div class="field-value">
                <a class="chip chip--link" href="<?= e(url('/timeline?focus=' . (int) ($entry['id'] ?? 0))) ?>">
                    <?= e(App\Calendar::formatEra($era['from'], $era['to'])) ?>
                </a>
            </div>
        <?php endif; ?>

    <?php elseif (FieldTypes::isRelation($type)): ?>
        <div class="chip-row">
            <?php foreach ($targets as $target): ?>
                <a class="chip chip--link"
                   href="<?= e(url('/c/' . $target['category_slug'] . '/e/' . $target['slug'])) ?>">
                    <span class="chip-icon"><?= e($target['category_icon'] ?: '•') ?></span>
                    <?= e($target['title']) ?>
                    <?php if (!empty($target['relation_type'])): ?>
                        <span class="chip-relation-type"><?= e($target['relation_type']) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <div class="field-value"><?= e($stored) ?></div>
    <?php endif; ?>
</div>
