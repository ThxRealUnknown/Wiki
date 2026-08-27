<?php

use App\FieldTypes;
use App\Settings;

/**
 * Renders the editing control for one layout field.
 *
 * @var array $field
 * @var array $values
 * @var array $links
 */

$fieldId = (int) $field['id'];
$type = (string) $field['field_type'];
$config = $field['config'] ?? [];
$stored = $values[$fieldId]['value_text'] ?? null;
$targets = $links[$fieldId] ?? [];
$name = 'fields[' . $fieldId . ']';
$domId = 'field-' . $fieldId;
$placeholder = (string) ($config['placeholder'] ?? '');

// Map disabled: hide visually rather than skip rendering — the hidden input
// must still post back, or saving would wipe the entry's existing shape/point.
$isMapField = in_array($type, [FieldTypes::MAPAREA, FieldTypes::MAPPOINT, FieldTypes::MAPPATH], true);
$hideMapField = $isMapField && !Settings::flag(Settings::FEATURE_MAP);
?>
<div class="field-block<?= ($field['width'] ?? 'full') === 'half' ? ' field-block--half' : '' ?>"
     <?= $hideMapField ? 'hidden' : '' ?>>
    <label class="field-label" for="<?= e($domId) ?>"><?= e($field['label']) ?></label>
    <?php if (!empty($field['help'])): ?>
        <p class="field-help"><?= e($field['help']) ?></p>
    <?php endif; ?>

    <?php switch ($type):
        case FieldTypes::TEXT: ?>
            <input class="input" type="text" id="<?= e($domId) ?>" name="<?= e($name) ?>"
                   value="<?= e($stored) ?>" placeholder="<?= e($placeholder) ?>">
            <?php break; ?>

        <?php case FieldTypes::DATE: ?>
            <?php
            $calConfig = App\Calendar::config();
            $date = App\Calendar::decode($stored);
            echo date_picker_html($name, $domId, $date, $calConfig);
            ?>
            <?php break; ?>

        <?php case FieldTypes::ERA: ?>
            <?php
            $calConfig = App\Calendar::config();
            $era = App\Calendar::decodeEra($stored) ?? ['from' => null, 'to' => null];
            ?>
            <div class="inline-row era-picker">
                <div>
                    <span class="field-help" style="display:block; margin-bottom:6px"><?= e(t('From')) ?></span>
                    <?= date_picker_html($name . '[from]', $domId, $era['from'], $calConfig) ?>
                </div>
                <div>
                    <span class="field-help" style="display:block; margin-bottom:6px"><?= e(t('To')) ?></span>
                    <?= date_picker_html($name . '[to]', $domId . '-to', $era['to'], $calConfig) ?>
                </div>
            </div>
            <p class="field-help">
                <?= e(t('Both ends are needed before this shows up on the timeline. A negative year is before %s.',
                    App\Timeline::epochName() !== '' ? App\Timeline::epochName() : t('the epoch'))) ?>
            </p>
            <?php break; ?>

        <?php case FieldTypes::NUMBER: ?>
            <div class="inline-row">
                <input class="input" type="text" inputmode="decimal" id="<?= e($domId) ?>"
                       name="<?= e($name) ?>" value="<?= e($stored) ?>">
                <?php if (!empty($config['unit'])): ?>
                    <span class="field-help" style="flex:0 0 auto; align-self:center; margin:0">
                        <?= e($config['unit']) ?>
                    </span>
                <?php endif; ?>
            </div>
            <?php break; ?>

        <?php case FieldTypes::TEXTAREA: ?>
            <textarea class="textarea" id="<?= e($domId) ?>" name="<?= e($name) ?>"
                      placeholder="<?= e($placeholder) ?>"><?= e($stored) ?></textarea>
            <?php break; ?>

        <?php case FieldTypes::RICHTEXT: ?>
            <div class="editor" data-editor>
                <div class="editor-toolbar">
                    <button type="button" class="editor-btn" data-cmd="bold" title="<?= e(t('Bold')) ?>"><b>B</b></button>
                    <button type="button" class="editor-btn" data-cmd="italic" title="<?= e(t('Italic')) ?>"><i>I</i></button>
                    <button type="button" class="editor-btn" data-cmd="underline" title="<?= e(t('Underline')) ?>"><u>U</u></button>
                    <button type="button" class="editor-btn" data-cmd="strikeThrough" title="<?= e(t('Strikethrough')) ?>"><s>S</s></button>
                    <span class="editor-sep"></span>
                    <button type="button" class="editor-btn" data-block="h2" title="<?= e(t('Heading')) ?>">H2</button>
                    <button type="button" class="editor-btn" data-block="h3" title="<?= e(t('Subheading')) ?>">H3</button>
                    <button type="button" class="editor-btn" data-block="p" title="<?= e(t('Paragraph')) ?>">¶</button>
                    <span class="editor-sep"></span>
                    <button type="button" class="editor-btn" data-cmd="insertUnorderedList" title="<?= e(t('Bullet list')) ?>">•—</button>
                    <button type="button" class="editor-btn" data-cmd="insertOrderedList" title="<?= e(t('Numbered list')) ?>">1.</button>
                    <button type="button" class="editor-btn" data-block="blockquote" title="<?= e(t('Quote')) ?>">❝</button>
                    <span class="editor-sep"></span>
                    <button type="button" class="editor-btn" data-align="left" title="<?= e(t('Align left')) ?>">⇤</button>
                    <button type="button" class="editor-btn" data-align="center" title="<?= e(t('Centre')) ?>">↔</button>
                    <button type="button" class="editor-btn" data-align="right" title="<?= e(t('Align right')) ?>">⇥</button>
                    <button type="button" class="editor-btn" data-align="justify" title="<?= e(t('Justify')) ?>">≡</button>
                    <span class="editor-sep"></span>
                    <button type="button" class="editor-btn" data-indent="out" title="<?= e(t('Decrease indent')) ?>">⇱</button>
                    <button type="button" class="editor-btn" data-indent="in" title="<?= e(t('Increase indent')) ?>">⇲</button>
                    <button type="button" class="editor-btn" data-first-line title="<?= e(t('First-line indent')) ?>">↳</button>
                    <span class="editor-sep"></span>
                    <button type="button" class="editor-btn" data-link title="<?= e(t('Link to an entry, or to an address (Ctrl+K)')) ?>">🔗</button>
                    <button type="button" class="editor-btn" data-cmd="removeFormat" title="<?= e(t('Clear formatting')) ?>">✕</button>
                </div>
                <div class="editor-surface prose" contenteditable="true" id="<?= e($domId) ?>"
                     data-placeholder="<?= e($placeholder !== '' ? $placeholder : t('Write…')) ?>"><?= $stored ?></div>
                <textarea name="<?= e($name) ?>" hidden data-editor-value><?= e($stored) ?></textarea>
            </div>
            <?php break; ?>

        <?php case FieldTypes::SELECT: ?>
            <?php $options = $config['options'] ?? []; ?>
            <select class="select" id="<?= e($domId) ?>" name="<?= e($name) ?>">
                <option value=""><?= e(t('— none —')) ?></option>
                <?php foreach ($options as $option): ?>
                    <option value="<?= e($option) ?>" <?= $stored === $option ? 'selected' : '' ?>>
                        <?= e($option) ?>
                    </option>
                <?php endforeach; ?>
                <?php if ($stored !== null && $stored !== '' && !in_array($stored, $options, true)): ?>
                    <?php // A value saved before the option list changed — keep it selectable. ?>
                    <option value="<?= e($stored) ?>" selected><?= e(t('%s (no longer offered)', $stored)) ?></option>
                <?php endif; ?>
            </select>
            <?php break; ?>

        <?php case FieldTypes::TAGS: ?>
            <?php
            $tags = json_decode((string) $stored, true) ?: [];
            $allowCustom = !empty($config['allow_custom']) || ($config['options'] ?? []) === [];

            // Layout-defined tags plus everything already used in this field,
            // so tags converge instead of duplicating.
            $suggestions = array_values(array_unique(array_merge(
                $config['options'] ?? [],
                (new App\EntryRepo())->tagsInUse($fieldId)
            )));
            sort($suggestions, SORT_NATURAL | SORT_FLAG_CASE);
            ?>
            <div class="tag-input" data-tag-input
                 data-name="<?= e($name) ?>[]"
                 data-suggestions="<?= e(json_encode($suggestions)) ?>"
                 data-allow-custom="<?= $allowCustom ? '1' : '0' ?>">
                <?php foreach ($tags as $tag): ?>
                    <span class="tag-pill">
                        <?= e((string) $tag) ?>
                        <input type="hidden" name="<?= e($name) ?>[]" value="<?= e((string) $tag) ?>">
                        <button type="button" class="tag-remove" aria-label="<?= e(t('Remove')) ?>">×</button>
                    </span>
                <?php endforeach; ?>
                <input class="tag-entry" type="text" id="<?= e($domId) ?>"
                       autocomplete="off"
                       placeholder="<?= $allowCustom ? e(t('Add a tag…')) : e(t('Pick a tag…')) ?>">
                <ul class="tag-menu" data-tag-menu hidden></ul>
            </div>
            <?php if ($suggestions !== []): ?>
                <p class="field-help tag-cloud-hint">
                    <?= e(t('In use:')) ?>
                    <?php foreach (array_slice($suggestions, 0, 12) as $option): ?>
                        <button type="button" class="tag-chip" data-tag-add="<?= e($option) ?>">
                            <?= e($option) ?>
                        </button>
                    <?php endforeach; ?>
                    <?php if (count($suggestions) > 12): ?>
                        <span><?= e(t('+%d more — start typing', count($suggestions) - 12)) ?></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <?php break; ?>

        <?php case FieldTypes::BANNER: ?>
        <?php case FieldTypes::IMAGE: ?>
            <div class="image-field">
                <?php if ($stored): ?>
                    <img class="image-preview" src="<?= e(url($stored)) ?>" alt="">
                <?php endif; ?>
                <div class="image-actions">
                    <input class="file-input" type="file" id="<?= e($domId) ?>"
                           name="field_image_<?= $fieldId ?>" accept="image/*">
                    <?php if ($stored): ?>
                        <label class="checkbox-row">
                            <input type="checkbox" name="field_image_remove[<?= $fieldId ?>]" value="1">
                            <?= e(t('Remove current image')) ?>
                        </label>
                    <?php endif; ?>
                </div>
            </div>
            <?php break; ?>

        <?php case FieldTypes::MAPAREA:
            // Assigned from the world map — this only shows or clears the assignment.
            $area = App\WorldMap::parseArea($stored);
            ?>
            <div class="maparea-ro" data-maparea-ro>
                <?php if ($area === null): ?>
                    <p class="field-help">
                        <?php // %s is markup, not escaped — intentional. ?>
                        <?= t('Not on the map yet. Shapes are traced on the <a href="%s">world map</a> and assigned to an entry from there.',
                            e(url('/map'))) ?>
                    </p>
                <?php else:
                    $layer = App\WorldMap::layer($area['layer']);
                    $maskId = 'edit-cut-' . $fieldId;
                    ?>
                    <svg class="mapcut-svg mapcut-svg--ro" data-mapcut
                         viewBox="0 0 <?= App\WorldMap::WIDTH ?> <?= App\WorldMap::HEIGHT ?>"
                         preserveAspectRatio="xMidYMid slice" role="img"
                         aria-label="<?= e($field['label']) ?>">
                        <defs>
                            <mask id="<?= e($maskId) ?>">
                                <rect width="100%" height="100%" fill="#fff"/>
                                <path d="<?= e($area['d']) ?>" fill="#000"/>
                            </mask>
                        </defs>
                        <?php if (!empty($layer['image'])): ?>
                            <image href="<?= e(asset($layer['image'])) ?>" x="0" y="0"
                                   width="<?= App\WorldMap::WIDTH ?>" height="<?= App\WorldMap::HEIGHT ?>"
                                   preserveAspectRatio="none"/>
                        <?php else: ?>
                            <rect width="100%" height="100%" class="mapcut-blank"/>
                        <?php endif; ?>
                        <rect width="100%" height="100%" class="mapcut-dim" mask="url(#<?= e($maskId) ?>)"/>
                        <path d="<?= e($area['d']) ?>" class="mapcut-shape"/>
                    </svg>

                    <div class="maparea-ro-bar">
                        <span class="field-help">
                            <?php // %s is markup, not escaped — intentional. ?>
                            <?= t('%s — drawn on the <a href="%s">world map</a>.',
                                e($layer['label'] ?? t('World map')),
                                e(url('/map?layer=' . $area['layer'] . '&focus=' . (int) ($entry['id'] ?? 0)))) ?>
                        </span>
                        <span class="spacer"></span>
                        <button type="button" class="btn btn--ghost btn--sm" data-maparea-clear>
                            <?= e(t('Remove shape')) ?>
                        </button>
                    </div>
                <?php endif; ?>

                <?php // The shape is not edited here — only kept or cleared. ?>
                <input type="hidden" id="<?= e($domId) ?>" name="<?= e($name) ?>"
                       value="<?= e((string) $stored) ?>" data-maparea-value>
            </div>
            <?php break; ?>

        <?php case FieldTypes::MAPPOINT:
            // Placed on the world map, like an area — read-only here.
            $point = App\WorldMap::parsePoint($stored);
            ?>
            <div class="maparea-ro" data-maparea-ro>
                <?php if ($point === null): ?>
                    <p class="field-help">
                        <?php // %s is markup, not escaped — intentional. ?>
                        <?= t('Not on the map yet. Points are placed on the <a href="%s">world map</a> and assigned from there.',
                            e(url('/map'))) ?>
                    </p>
                <?php else:
                    $layer = App\WorldMap::layer($point['layer']);
                    $half = App\WorldMap::POINT_WINDOW / 2;
                    $vx = max(0, min(App\WorldMap::WIDTH - App\WorldMap::POINT_WINDOW, $point['x'] - $half));
                    $vy = max(0, min(App\WorldMap::HEIGHT - App\WorldMap::POINT_WINDOW, $point['y'] - $half));
                    ?>
                    <svg class="mapcut-svg mapcut-svg--ro"
                         viewBox="<?= (int) $vx ?> <?= (int) $vy ?> <?= App\WorldMap::POINT_WINDOW ?> <?= App\WorldMap::POINT_WINDOW ?>"
                         preserveAspectRatio="xMidYMid slice" role="img"
                         aria-label="<?= e($field['label']) ?>">
                        <?php if (!empty($layer['image'])): ?>
                            <image href="<?= e(asset($layer['image'])) ?>" x="0" y="0"
                                   width="<?= App\WorldMap::WIDTH ?>" height="<?= App\WorldMap::HEIGHT ?>"
                                   preserveAspectRatio="none"/>
                        <?php else: ?>
                            <rect width="100%" height="100%" class="mapcut-blank"/>
                        <?php endif; ?>
                        <circle cx="<?= (float) $point['x'] ?>" cy="<?= (float) $point['y'] ?>"
                                r="18" class="mapcut-here"/>
                    </svg>

                    <div class="maparea-ro-bar">
                        <span class="field-help">
                            <?php // %s is markup, not escaped — intentional. ?>
                            <?= t('%s — %d, %d. Placed on the <a href="%s">world map</a>.',
                                e($layer['label'] ?? t('World map')),
                                (int) $point['x'], (int) $point['y'],
                                e(url('/map?layer=' . $point['layer'] . '&focus=' . (int) ($entry['id'] ?? 0)))) ?>
                        </span>
                        <span class="spacer"></span>
                        <button type="button" class="btn btn--ghost btn--sm" data-maparea-clear>
                            <?= e(t('Remove point')) ?>
                        </button>
                    </div>
                <?php endif; ?>

                <input type="hidden" id="<?= e($domId) ?>" name="<?= e($name) ?>"
                       value="<?= e((string) $stored) ?>" data-maparea-value>
            </div>
            <?php break; ?>

        <?php case FieldTypes::MAPPATH:
            // Routes are drawn on the world map too — read-only here, like area.
            $mapPath = App\WorldMap::parsePath($stored);
            ?>
            <div class="maparea-ro" data-maparea-ro>
                <?php if ($mapPath === null): ?>
                    <p class="field-help">
                        <?php // %s is markup, not escaped — intentional. ?>
                        <?= t('Not on the map yet. Paths are traced on the <a href="%s">world map</a> and assigned to an entry from there.',
                            e(url('/map'))) ?>
                    </p>
                <?php else:
                    $layer = App\WorldMap::layer($mapPath['layer']);
                    ?>
                    <svg class="mapcut-svg mapcut-svg--ro" data-mapcut
                         data-path="<?= e($mapPath['d']) ?>"
                         viewBox="0 0 <?= App\WorldMap::WIDTH ?> <?= App\WorldMap::HEIGHT ?>"
                         preserveAspectRatio="xMidYMid slice" role="img"
                         aria-label="<?= e($field['label']) ?>">
                        <?php if (!empty($layer['image'])): ?>
                            <image href="<?= e(asset($layer['image'])) ?>" x="0" y="0"
                                   width="<?= App\WorldMap::WIDTH ?>" height="<?= App\WorldMap::HEIGHT ?>"
                                   preserveAspectRatio="none"/>
                        <?php else: ?>
                            <rect width="100%" height="100%" class="mapcut-blank"/>
                        <?php endif; ?>
                        <path d="<?= e($mapPath['d']) ?>" class="mapcut-shape mapcut-shape--line"/>
                    </svg>

                    <div class="maparea-ro-bar">
                        <span class="field-help">
                            <?php // %s is markup, not escaped — intentional. ?>
                            <?= t('%s — drawn on the <a href="%s">world map</a>.',
                                e($layer['label'] ?? t('World map')),
                                e(url('/map?layer=' . $mapPath['layer'] . '&focus=' . (int) ($entry['id'] ?? 0)))) ?>
                        </span>
                        <span class="spacer"></span>
                        <button type="button" class="btn btn--ghost btn--sm" data-maparea-clear>
                            <?= e(t('Remove path')) ?>
                        </button>
                    </div>
                <?php endif; ?>

                <input type="hidden" id="<?= e($domId) ?>" name="<?= e($name) ?>"
                       value="<?= e((string) $stored) ?>" data-maparea-value>
            </div>
            <?php break; ?>

        <?php case FieldTypes::RELATION: ?>
            <?php
            $multiple = !empty($config['multiple']);
            $targetCategories = FieldTypes::relationTargets($config);
            $configured = $targetCategories !== [];
            $typed = FieldTypes::isTypedRelation($config);
            $relationTypeOptions = $config['types'] ?? [];
            ?>
            <div class="relation-field" data-relation
                 data-name="<?= e($name) ?>[]"
                 data-field-id="<?= $fieldId ?>"
                 data-multiple="<?= $multiple ? '1' : '0' ?>"
                 data-category="<?= e(implode(',', $targetCategories)) ?>"
                 data-exclude="<?= isset($entry['id']) ? (int) $entry['id'] : 0 ?>"
                 data-endpoint="<?= e(url('/api/lookup')) ?>"
                 data-typed="<?= $typed ? '1' : '0' ?>"
                 data-relation-types="<?= e(json_encode($relationTypeOptions)) ?>">
                <div class="relation-selected" data-relation-selected>
                    <?php // Kept even when unconfigured — without these, saving would drop existing links. ?>
                    <?php foreach ($targets as $target): ?>
                        <span class="tag-pill">
                            <span class="chip-icon"><?= e($target['category_icon'] ?: '•') ?></span>
                            <?= e($target['title']) ?>
                            <?php if ($typed): ?>
                                <select class="relation-type-select" name="relation_types[<?= $fieldId ?>][]">
                                    <option value=""><?= e(t('— type —')) ?></option>
                                    <?php foreach ($relationTypeOptions as $option): ?>
                                        <option value="<?= e($option) ?>"
                                            <?= ($target['relation_type'] ?? '') === $option ? 'selected' : '' ?>>
                                            <?= e($option) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                            <input type="hidden" name="<?= e($name) ?>[]" value="<?= (int) $target['id'] ?>">
                            <button type="button" class="tag-remove" aria-label="<?= e(t('Remove')) ?>">×</button>
                        </span>
                    <?php endforeach; ?>
                </div>

                <?php if ($configured): ?>
                    <input class="relation-search" type="text" id="<?= e($domId) ?>" autocomplete="off"
                           placeholder="<?= $multiple ? e(t('Search entries to link…')) : e(t('Search for an entry…')) ?>"
                           data-relation-search>
                    <ul class="relation-results" data-relation-results hidden></ul>
                <?php else: ?>
                    <p class="relation-unset">
                        <?= e(t('No link defined.')) ?>
                        <?php if (isset($category, $layout)): ?>
                            <?php // %s is markup, not escaped — intentional. ?>
                            <?= t('<a href="%s">Choose which archives this field points at</a> in the layout.',
                                e(url('/c/' . $category['slug'] . '/layouts/' . $layout['id']))) ?>
                        <?php else: ?>
                            <?= e(t('Choose which archives this field points at in the layout.')) ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
            <?php break; ?>

        <?php default: ?>
            <input class="input" type="text" id="<?= e($domId) ?>" name="<?= e($name) ?>"
                   value="<?= e($stored) ?>">
    <?php endswitch; ?>
</div>
