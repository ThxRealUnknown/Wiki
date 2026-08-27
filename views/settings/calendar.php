<?php

/**
 * Designing the calendar: months, weekdays, and two kinds of exception —
 * intercalary day blocks that sit outside any month, and periodic leap
 * adjustments to a month's length.
 *
 * @var array $config from App\Calendar::config()
 */
?>
<div class="single-col">
    <div class="page" data-calendar-settings>
        <div class="page-head">
            <div class="crumbs">
                <a href="<?= e(url('/settings')) ?>"><?= e(t('Settings')) ?></a> › <span><?= e(t('Calendar')) ?></span>
            </div>
            <h1 class="page-title"><?= e(t('Calendar')) ?></h1>
            <p class="lede">
                <?php // %s is markup, not escaped — intentional. ?>
                <?= t('A calendar of your own design — months, weekdays, and the exceptions that make it feel real. Every Date and Era field, and the <a href="%s">calendar view</a> on the Timeline, follow whatever is set up here.',
                    e(url('/timeline/calendar'))) ?>
            </p>
        </div>

        <?php // Read by the datepicker JS (a Cycle holiday's reference date) —
              // same convention as views/entries/form.php and
              // views/timeline/calendar.php. ?>
        <script type="application/json" data-calendar-config>
            <?= json_encode($config, JSON_UNESCAPED_UNICODE) ?>
        </script>

        <form method="post" action="<?= e(url('/settings/calendar')) ?>">
            <?= csrf_field() ?>

            <div class="section" style="margin-top:0">
                <h2 class="section-title"><?= e(t('Months')) ?></h2>
                <p class="field-help" style="max-width:62ch; margin-bottom:14px">
                    <?= e(t('In order — the first is where a year starts. Each has its own length; a leap rule below can add or remove days from one in years that match it.')) ?>
                </p>

                <ul class="field-rows" data-repeat-list="months">
                    <?php foreach ($config['months'] as $i => $month): ?>
                        <li class="cal-row" data-repeat-row draggable="false">
                            <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>
                            <input class="input" type="text" placeholder="<?= e(t('Month name')) ?>"
                                   name="months[<?= $i ?>][name]" value="<?= e($month['name']) ?>"
                                   data-name-template="months[__i__][name]">
                            <input class="input" type="text" inputmode="numeric" style="max-width:90px"
                                   placeholder="<?= e(t('Days')) ?>" name="months[<?= $i ?>][days]"
                                   value="<?= (int) $month['days'] ?>"
                                   data-name-template="months[__i__][days]">
                            <button type="button" class="icon-btn" data-remove-row title="<?= e(t('Remove this month')) ?>">✕</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn btn--sm" data-repeat-add="months">＋ <?= e(t('Add a month')) ?></button>

                <template data-repeat-template="months">
                    <li class="cal-row" data-repeat-row>
                        <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>
                        <input class="input" type="text" placeholder="<?= e(t('Month name')) ?>"
                               data-name-template="months[__i__][name]">
                        <input class="input" type="text" inputmode="numeric" style="max-width:90px"
                               placeholder="<?= e(t('Days')) ?>" data-name-template="months[__i__][days]">
                        <button type="button" class="icon-btn" data-remove-row title="<?= e(t('Remove this month')) ?>">✕</button>
                    </li>
                </template>
            </div>

            <div class="section">
                <h2 class="section-title"><?= e(t('Weekdays')) ?></h2>
                <p class="field-help" style="max-width:62ch; margin-bottom:14px">
                    <?= e(t('In order. How many there are decides the week the calendar view is drawn on.')) ?>
                </p>

                <ul class="field-rows" data-repeat-list="weekdays">
                    <?php foreach ($config['weekdays'] as $i => $name): ?>
                        <li class="cal-row" data-repeat-row draggable="false">
                            <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>
                            <input class="input" type="text" placeholder="<?= e(t('Weekday name')) ?>"
                                   name="weekdays[<?= $i ?>][name]" value="<?= e($name) ?>"
                                   data-name-template="weekdays[__i__][name]">
                            <button type="button" class="icon-btn" data-remove-row title="<?= e(t('Remove this weekday')) ?>">✕</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn btn--sm" data-repeat-add="weekdays">＋ <?= e(t('Add a weekday')) ?></button>

                <template data-repeat-template="weekdays">
                    <li class="cal-row" data-repeat-row>
                        <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>
                        <input class="input" type="text" placeholder="<?= e(t('Weekday name')) ?>"
                               data-name-template="weekdays[__i__][name]">
                        <button type="button" class="icon-btn" data-remove-row title="<?= e(t('Remove this weekday')) ?>">✕</button>
                    </li>
                </template>
            </div>

            <div class="section">
                <h2 class="section-title"><?= e(t('Special days')) ?></h2>
                <p class="field-help" style="max-width:64ch; margin-bottom:14px">
                    <?= e(t('Days that sit outside every month — a year-end festival, a set of unlucky days. "After month" 0 means before the first month; the highest number means after the last. An entry can be dated to one of these days directly, the same as any ordinary day.')) ?>
                </p>

                <ul class="field-rows" data-repeat-list="intercalary">
                    <?php foreach ($config['intercalary'] as $i => $block): ?>
                        <li class="cal-row cal-row--wide" data-repeat-row draggable="false">
                            <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>
                            <input class="input" type="text" placeholder="<?= e(t('Name, e.g. Yearsend')) ?>"
                                   name="intercalary[<?= $i ?>][name]" value="<?= e($block['name']) ?>"
                                   data-name-template="intercalary[__i__][name]">
                            <input class="input" type="text" inputmode="numeric" style="max-width:80px"
                                   placeholder="<?= e(t('Days')) ?>" name="intercalary[<?= $i ?>][days]"
                                   value="<?= (int) $block['days'] ?>"
                                   data-name-template="intercalary[__i__][days]">
                            <label class="field-help" style="white-space:nowrap">
                                <?= e(t('After month')) ?>
                                <input class="input" type="text" inputmode="numeric" style="width:60px; display:inline-block"
                                       name="intercalary[<?= $i ?>][after_month]" value="<?= (int) $block['after_month'] ?>"
                                       data-name-template="intercalary[__i__][after_month]">
                            </label>
                            <label class="checkbox-row" style="white-space:nowrap">
                                <input type="checkbox" value="1"
                                       name="intercalary[<?= $i ?>][counts_weekday]"
                                       data-name-template="intercalary[__i__][counts_weekday]"
                                       <?= !empty($block['counts_weekday']) ? 'checked' : '' ?>>
                                <?= e(t('Counts toward weekdays')) ?>
                            </label>
                            <button type="button" class="icon-btn" data-remove-row title="<?= e(t('Remove these days')) ?>">✕</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn btn--sm" data-repeat-add="intercalary">＋ <?= e(t('Add special days')) ?></button>

                <template data-repeat-template="intercalary">
                    <li class="cal-row cal-row--wide" data-repeat-row>
                        <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>
                        <input class="input" type="text" placeholder="<?= e(t('Name, e.g. Yearsend')) ?>"
                               data-name-template="intercalary[__i__][name]">
                        <input class="input" type="text" inputmode="numeric" style="max-width:80px"
                               placeholder="<?= e(t('Days')) ?>" data-name-template="intercalary[__i__][days]">
                        <label class="field-help" style="white-space:nowrap">
                            <?= e(t('After month')) ?>
                            <input class="input" type="text" inputmode="numeric" style="width:60px; display:inline-block"
                                   value="0" data-name-template="intercalary[__i__][after_month]">
                        </label>
                        <label class="checkbox-row" style="white-space:nowrap">
                            <input type="checkbox" value="1" data-name-template="intercalary[__i__][counts_weekday]">
                            <?= e(t('Counts toward weekdays')) ?>
                        </label>
                        <button type="button" class="icon-btn" data-remove-row title="<?= e(t('Remove these days')) ?>">✕</button>
                    </li>
                </template>
            </div>

            <div class="section">
                <h2 class="section-title"><?= e(t('Leap rules')) ?></h2>
                <p class="field-help" style="max-width:64ch; margin-bottom:14px">
                    <?= e(t('"Every N years, starting at year offset O, month M gains (or loses) some number of days." Add more than one to combine effects — a rule with negative days undoes another for years that match both.')) ?>
                </p>

                <ul class="field-rows" data-repeat-list="leap_rules">
                    <?php foreach ($config['leap_rules'] as $i => $rule): ?>
                        <li class="cal-row cal-row--wide" data-repeat-row draggable="false">
                            <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>
                            <input class="input" type="text" placeholder="<?= e(t('Name')) ?>"
                                   name="leap_rules[<?= $i ?>][name]" value="<?= e($rule['name']) ?>"
                                   data-name-template="leap_rules[__i__][name]">
                            <label class="field-help" style="white-space:nowrap">
                                <?= e(t('Every')) ?>
                                <input class="input" type="text" inputmode="numeric" style="width:60px; display:inline-block"
                                       name="leap_rules[<?= $i ?>][every_years]" value="<?= (int) $rule['every_years'] ?>"
                                       data-name-template="leap_rules[__i__][every_years]">
                                <?= e(t('years, offset')) ?>
                                <input class="input" type="text" inputmode="numeric" style="width:60px; display:inline-block"
                                       name="leap_rules[<?= $i ?>][offset]" value="<?= (int) $rule['offset'] ?>"
                                       data-name-template="leap_rules[__i__][offset]">
                            </label>
                            <label class="field-help" style="white-space:nowrap">
                                <?= e(t('Month')) ?>
                                <input class="input" type="text" inputmode="numeric" style="width:60px; display:inline-block"
                                       name="leap_rules[<?= $i ?>][month]" value="<?= (int) $rule['month'] ?>"
                                       data-name-template="leap_rules[__i__][month]">
                                <?= e(t('gets')) ?>
                                <input class="input" type="text" inputmode="numeric" style="width:70px; display:inline-block"
                                       name="leap_rules[<?= $i ?>][extra_days]" value="<?= (int) $rule['extra_days'] ?>"
                                       data-name-template="leap_rules[__i__][extra_days]">
                                <?= e(t('days')) ?>
                            </label>
                            <button type="button" class="icon-btn" data-remove-row title="<?= e(t('Remove this rule')) ?>">✕</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn btn--sm" data-repeat-add="leap_rules">＋ <?= e(t('Add a leap rule')) ?></button>

                <template data-repeat-template="leap_rules">
                    <li class="cal-row cal-row--wide" data-repeat-row>
                        <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>
                        <input class="input" type="text" placeholder="<?= e(t('Name')) ?>"
                               data-name-template="leap_rules[__i__][name]">
                        <label class="field-help" style="white-space:nowrap">
                            <?= e(t('Every')) ?>
                            <input class="input" type="text" inputmode="numeric" style="width:60px; display:inline-block"
                                   value="4" data-name-template="leap_rules[__i__][every_years]">
                            <?= e(t('years, offset')) ?>
                            <input class="input" type="text" inputmode="numeric" style="width:60px; display:inline-block"
                                   value="0" data-name-template="leap_rules[__i__][offset]">
                        </label>
                        <label class="field-help" style="white-space:nowrap">
                            <?= e(t('Month')) ?>
                            <input class="input" type="text" inputmode="numeric" style="width:60px; display:inline-block"
                                   value="1" data-name-template="leap_rules[__i__][month]">
                            <?= e(t('gets')) ?>
                            <input class="input" type="text" inputmode="numeric" style="width:70px; display:inline-block"
                                   value="1" data-name-template="leap_rules[__i__][extra_days]">
                            <?= e(t('days')) ?>
                        </label>
                        <button type="button" class="icon-btn" data-remove-row title="<?= e(t('Remove this rule')) ?>">✕</button>
                    </li>
                </template>
            </div>

            <div class="section">
                <h2 class="section-title"><?= e(t('Holidays')) ?></h2>
                <p class="field-help" style="max-width:64ch; margin-bottom:14px">
                    <?php // %s is markup, not escaped — intentional. ?>
                    <?= t("A named day that recurs every year — a founding day, a festival. Shown on the <a href=\"%s\">calendar view</a> for whichever year you're looking at. Purely a label — it doesn't change month lengths or weekdays the way a leap rule or special days can.",
                        e(url('/timeline/calendar'))) ?>
                </p>

                <?php
                // The Months/Special-days picker the "Fixed date" fields use —
                // built once and reused, since it never depends on the row.
                $slotOptions = static function (?array $selected = null) use ($config): string {
                    $selectedValue = $selected !== null ? App\Calendar::slotValue($selected) : '';
                    $html = '<optgroup label="' . e(t('Months')) . '">';
                    foreach ($config['months'] as $i => $month) {
                        $value = 'm:' . ($i + 1);
                        $html .= '<option value="' . e($value) . '"'
                            . ($selectedValue === $value ? ' selected' : '') . '>'
                            . e($month['name']) . '</option>';
                    }
                    $html .= '</optgroup>';

                    if ($config['intercalary'] !== []) {
                        $html .= '<optgroup label="' . e(t('Special days')) . '">';
                        foreach ($config['intercalary'] as $i => $block) {
                            $value = 'i:' . $i;
                            $html .= '<option value="' . e($value) . '"'
                                . ($selectedValue === $value ? ' selected' : '') . '>'
                                . e($block['name']) . '</option>';
                        }
                        $html .= '</optgroup>';
                    }

                    return $html;
                };

                // 0 means every month; no Special-days group here, since a
                // block may have no weekdays of its own.
                $monthOptions = static function (int $selected = 0) use ($config): string {
                    $html = '<option value="0"' . ($selected === 0 ? ' selected' : '') . '>' . e(t('Every month')) . '</option>';
                    foreach ($config['months'] as $i => $month) {
                        $value = $i + 1;
                        $html .= '<option value="' . $value . '"' . ($selected === $value ? ' selected' : '') . '>'
                            . e($month['name']) . '</option>';
                    }

                    return $html;
                };

                $occurrenceOptions = static function (int $selected = 1): string {
                    $labels = [
                        1 => t('1st'), 2 => t('2nd'), 3 => t('3rd'), 4 => t('4th'), 5 => t('5th'), -1 => t('Last'),
                    ];
                    $html = '';
                    foreach ($labels as $value => $label) {
                        $html .= '<option value="' . $value . '"' . ($selected === $value ? ' selected' : '') . '>'
                            . e($label) . '</option>';
                    }

                    return $html;
                };

                $weekdayOptions = static function (int $selected = 0) use ($config): string {
                    $html = '';
                    foreach ($config['weekdays'] as $i => $name) {
                        $html .= '<option value="' . $i . '"' . ($selected === $i ? ' selected' : '') . '>'
                            . e($name) . '</option>';
                    }

                    return $html;
                };
                ?>

                <ul class="field-rows" data-repeat-list="holidays">
                    <?php foreach ($config['holidays'] as $i => $holiday): ?>
                        <?php $type = $holiday['type'] ?? 'date'; ?>
                        <li class="cal-row cal-row--wide" data-repeat-row data-holiday-row draggable="false">
                            <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>
                            <input class="input" type="text" placeholder="<?= e(t('Name, e.g. Founding Day')) ?>"
                                   name="holidays[<?= $i ?>][name]" value="<?= e($holiday['name']) ?>"
                                   data-name-template="holidays[__i__][name]">
                            <select class="select" style="max-width:140px" data-holiday-type
                                    name="holidays[<?= $i ?>][type]" data-name-template="holidays[__i__][type]">
                                <option value="date" <?= $type === 'date' ? 'selected' : '' ?>><?= e(t('Fixed date')) ?></option>
                                <option value="weekday" <?= $type === 'weekday' ? 'selected' : '' ?>><?= e(t('Weekday rule')) ?></option>
                                <option value="cycle" <?= $type === 'cycle' ? 'selected' : '' ?>><?= e(t('Cycle')) ?></option>
                            </select>

                            <span class="holiday-fields" data-holiday-for="date">
                                <select class="select" style="max-width:170px"
                                        name="holidays[<?= $i ?>][slot]" data-name-template="holidays[__i__][slot]">
                                    <?= $slotOptions($type === 'date' ? $holiday : null) ?>
                                </select>
                                <input class="input" type="text" inputmode="numeric" style="max-width:70px"
                                       placeholder="<?= e(t('Day')) ?>" name="holidays[<?= $i ?>][day]"
                                       value="<?= $type === 'date' ? (int) $holiday['day'] : '' ?>"
                                       data-name-template="holidays[__i__][day]">
                            </span>

                            <span class="holiday-fields" data-holiday-for="weekday">
                                <select class="select" style="max-width:130px"
                                        name="holidays[<?= $i ?>][month]" data-name-template="holidays[__i__][month]">
                                    <?= $monthOptions($type === 'weekday' ? (int) ($holiday['month'] ?? 0) : 0) ?>
                                </select>
                                <select class="select" style="max-width:90px"
                                        name="holidays[<?= $i ?>][occurrence]" data-name-template="holidays[__i__][occurrence]">
                                    <?= $occurrenceOptions($type === 'weekday' ? (int) ($holiday['occurrence'] ?? 1) : 1) ?>
                                </select>
                                <select class="select" style="max-width:130px"
                                        name="holidays[<?= $i ?>][weekday]" data-name-template="holidays[__i__][weekday]">
                                    <?= $weekdayOptions($type === 'weekday' ? (int) ($holiday['weekday'] ?? 0) : 0) ?>
                                </select>
                            </span>

                            <span class="holiday-fields" data-holiday-for="cycle">
                                <label class="field-help" style="white-space:nowrap">
                                    <?= e(t('Every')) ?>
                                    <input class="input" type="text" inputmode="numeric" style="width:60px; display:inline-block"
                                           name="holidays[<?= $i ?>][every_days]" data-name-template="holidays[__i__][every_days]"
                                           value="<?= $type === 'cycle' ? (int) $holiday['every_days'] : 28 ?>">
                                    <?= e(t('days, starting')) ?>
                                </label>
                                <?= date_picker_html(
                                    'holidays[' . $i . '][start]',
                                    'holiday-start-' . $i,
                                    $type === 'cycle' ? ($holiday['start'] ?? null) : null,
                                    $config
                                ) ?>
                            </span>

                            <button type="button" class="icon-btn" data-remove-row title="<?= e(t('Remove this holiday')) ?>">✕</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn btn--sm" data-repeat-add="holidays">＋ <?= e(t('Add a holiday')) ?></button>

                <template data-repeat-template="holidays">
                    <li class="cal-row cal-row--wide" data-repeat-row data-holiday-row>
                        <span class="drag-handle" data-drag-handle title="<?= e(t('Drag to reorder')) ?>">⠿</span>
                        <input class="input" type="text" placeholder="<?= e(t('Name, e.g. Founding Day')) ?>"
                               data-name-template="holidays[__i__][name]">
                        <select class="select" style="max-width:140px" data-holiday-type
                                data-name-template="holidays[__i__][type]">
                            <option value="date" selected><?= e(t('Fixed date')) ?></option>
                            <option value="weekday"><?= e(t('Weekday rule')) ?></option>
                            <option value="cycle"><?= e(t('Cycle')) ?></option>
                        </select>

                        <span class="holiday-fields" data-holiday-for="date">
                            <select class="select" style="max-width:170px" data-name-template="holidays[__i__][slot]">
                                <?= $slotOptions() ?>
                            </select>
                            <input class="input" type="text" inputmode="numeric" style="max-width:70px"
                                   placeholder="<?= e(t('Day')) ?>" value="1" data-name-template="holidays[__i__][day]">
                        </span>

                        <span class="holiday-fields" data-holiday-for="weekday" hidden>
                            <select class="select" style="max-width:130px" data-name-template="holidays[__i__][month]">
                                <?= $monthOptions() ?>
                            </select>
                            <select class="select" style="max-width:90px" data-name-template="holidays[__i__][occurrence]">
                                <?= $occurrenceOptions() ?>
                            </select>
                            <select class="select" style="max-width:130px" data-name-template="holidays[__i__][weekday]">
                                <?= $weekdayOptions() ?>
                            </select>
                        </span>

                        <span class="holiday-fields" data-holiday-for="cycle" hidden>
                            <label class="field-help" style="white-space:nowrap">
                                <?= e(t('Every')) ?>
                                <input class="input" type="text" inputmode="numeric" style="width:60px; display:inline-block"
                                       value="28" data-name-template="holidays[__i__][every_days]">
                                <?= e(t('days, starting')) ?>
                            </label>
                            <?= date_picker_html('holidays[__i__][start]', 'holiday-start-new', null, $config) ?>
                        </span>

                        <button type="button" class="icon-btn" data-remove-row title="<?= e(t('Remove this holiday')) ?>">✕</button>
                    </li>
                </template>
            </div>

            <div class="form-bar">
                <button class="btn btn--primary" type="submit"><?= e(t('Save calendar')) ?></button>
                <span class="field-help" data-dirty-hint hidden><?= e(t('Unsaved changes')) ?></span>
            </div>
        </form>
    </div>
</div>
