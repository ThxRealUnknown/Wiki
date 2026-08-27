<?php
/**
 * @var string|null $banner   relative path under public/, or null
 * @var array       $tags     every tag in use, from TagRepo
 * @var array       $features  ['book' => bool, 'map' => bool, 'connections' => bool, 'timeline' => bool]
 * @var string      $epochName the wiki's calendar, e.g. "After the Fracture"
 * @var string      $epochAbbr its abbreviation, e.g. "A.F."
 * @var string      $locale    the current language code
 * @var array       $locales   every language on offer, code => native name
 * @var string      $draftGoal a target total word count for the draft, or ''
 */
?>
<div class="single-col">
    <div class="page">
        <div class="page-head">
            <h1 class="page-title"><?= e(t('Settings')) ?></h1>
            <p class="lede"><?= e(t('Things the site holds about itself.')) ?></p>
        </div>

        <div class="section" style="margin-top:0">
            <h2 class="section-title"><?= e(t('Features')) ?></h2>
            <p class="field-help" style="max-width:62ch; margin-bottom:14px">
                <?= e(t("Turn off parts of the app you're not using. Turning one back on picks up right where it left off — nothing is deleted while it's hidden.")) ?>
            </p>

            <form method="post" action="<?= e(url('/settings/features')) ?>">
                <?= csrf_field() ?>
                <label class="switch-row">
                    <input type="checkbox" name="feature_book" value="1"
                           <?= $features['book'] ? 'checked' : '' ?>>
                    <span><?= e(t('Book — Draft and Story in the sidebar')) ?></span>
                </label>
                <label class="switch-row">
                    <input type="checkbox" name="feature_map" value="1"
                           <?= $features['map'] ? 'checked' : '' ?>>
                    <span><?= e(t('Map — the World map, and Map area/Map point fields')) ?></span>
                </label>
                <label class="switch-row">
                    <input type="checkbox" name="feature_connections" value="1"
                           <?= $features['connections'] ? 'checked' : '' ?>>
                    <span><?= e(t('Connections — the Pinboard and hand-drawn connections')) ?></span>
                </label>
                <label class="switch-row">
                    <input type="checkbox" name="feature_timeline" value="1"
                           <?= $features['timeline'] ? 'checked' : '' ?>>
                    <span><?= e(t('Timeline — chronology from Date and Era fields')) ?></span>
                </label>
                <div class="page-actions">
                    <button class="btn btn--primary" type="submit"><?= e(t('Save')) ?></button>
                </div>
            </form>
        </div>

        <div class="section">
            <h2 class="section-title"><?= e(t('Timeline')) ?></h2>
            <p class="field-help" style="max-width:62ch; margin-bottom:14px">
                <?= e(t('What your own calendar is called, and how a year is abbreviated — used on the timeline and everywhere a Date or Era field shows a year, e.g. "204 %s". A year before it starts is written with a minus sign: "−58 %s".',
                    $epochAbbr !== '' ? $epochAbbr : 'T.A.', $epochAbbr !== '' ? $epochAbbr : 'T.A.')) ?>
            </p>

            <form method="post" action="<?= e(url('/settings/timeline')) ?>">
                <?= csrf_field() ?>
                <div class="inline-row" style="max-width:460px">
                    <div>
                        <label class="field-label" for="epoch-name"><?= e(t('Calendar name')) ?></label>
                        <input class="input" id="epoch-name" type="text" name="epoch_name"
                               value="<?= e($epochName) ?>" placeholder="Third Age">
                    </div>
                    <div style="flex:0 0 130px">
                        <label class="field-label" for="epoch-abbr"><?= e(t('Abbreviation')) ?></label>
                        <input class="input" id="epoch-abbr" type="text" name="epoch_abbr"
                               value="<?= e($epochAbbr) ?>" placeholder="T.A.">
                    </div>
                </div>
                <div class="page-actions">
                    <button class="btn btn--primary" type="submit"><?= e(t('Save')) ?></button>
                    <a class="btn btn--ghost" href="<?= e(url('/settings/calendar')) ?>">
                        <?= e(t('Design the calendar →')) ?>
                    </a>
                </div>
            </form>
        </div>

        <div class="section">
            <h2 class="section-title"><?= e(t('Writing goal')) ?></h2>
            <p class="field-help" style="max-width:62ch; margin-bottom:14px">
                <?= e(t('A target word count for the whole draft. Set one and the Draft page shows progress toward it; leave it blank and the word counts show with nothing to measure against.')) ?>
            </p>

            <form method="post" action="<?= e(url('/settings/goal')) ?>">
                <?= csrf_field() ?>
                <div class="inline-row" style="max-width:320px">
                    <input class="input" type="text" inputmode="numeric" name="draft_goal_words"
                           value="<?= e($draftGoal) ?>" placeholder="<?= e(t('e.g. 80000')) ?>">
                    <button class="btn btn--primary" type="submit" style="flex:0 0 auto"><?= e(t('Save')) ?></button>
                </div>
            </form>
        </div>

        <div class="section">
            <h2 class="section-title"><?= e(t('Language')) ?></h2>
            <p class="field-help" style="max-width:62ch; margin-bottom:14px">
                <?= e(t('Choose which language the interface is shown in.')) ?>
            </p>

            <form method="post" action="<?= e(url('/settings/language')) ?>">
                <?= csrf_field() ?>
                <div class="inline-row" style="max-width:320px">
                    <select class="select" name="locale">
                        <?php foreach ($locales as $code => $name): ?>
                            <option value="<?= e($code) ?>" <?= $code === $locale ? 'selected' : '' ?>>
                                <?= e($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn--primary" type="submit" style="flex:0 0 auto"><?= e(t('Save')) ?></button>
                </div>
            </form>
        </div>

        <div class="section">
            <h2 class="section-title"><?= e(t('Site banner')) ?></h2>
            <p class="field-help" style="max-width:62ch; margin-bottom:14px">
                <?php // &nbsp; is a raw HTML entity, not escaped — intentional. ?>
                <?= t('A wide image across the very top of every page, above the sidebar and the content. Something roughly 2000%s×%s300 works well — it is cropped to fit, centred, so keep anything important away from the edges.',
                    '&nbsp;', '&nbsp;') ?>
            </p>

            <?php if ($banner !== null): ?>
                <div class="banner-preview">
                    <img src="<?= e(url($banner)) ?>" alt="<?= e(t('The current site banner')) ?>">
                </div>
            <?php else: ?>
                <p class="field-help"><em><?= e(t('No banner set — the top of the page is plain.')) ?></em></p>
            <?php endif; ?>

            <form method="post" action="<?= e(url('/settings/banner')) ?>"
                  enctype="multipart/form-data" style="margin-top:16px; max-width:520px">
                <?= csrf_field() ?>
                <label class="field-label" for="site-banner">
                    <?= $banner !== null ? e(t('Replace it')) : e(t('Choose an image')) ?>
                </label>
                <input class="file-input" id="site-banner" type="file" name="site_banner"
                       accept="image/*">
                <div class="page-actions">
                    <button class="btn btn--primary" type="submit">
                        <?= $banner !== null ? e(t('Replace banner')) : e(t('Set banner')) ?>
                    </button>
                </div>
            </form>

            <?php if ($banner !== null): ?>
                <form method="post" action="<?= e(url('/settings/banner')) ?>"
                      style="margin-top:10px"
                      data-confirm="<?= e(t('Remove the site banner?')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="remove" value="1">
                    <button class="btn btn--sm btn--danger" type="submit"><?= e(t('Remove the banner')) ?></button>
                </form>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2 class="section-title"><?= e(t('Tags — %d', count($tags))) ?></h2>
            <p class="field-help" style="max-width:64ch; margin-bottom:14px">
                <?php // %s is markup, not escaped — intentional. ?>
                <?= t('Every tag in use, across all archives. A tag exists only as long as an entry uses it — remove the last one and it disappears here too. Deleting one below strips it from %s entry at once.',
                    '<strong>' . e(t('every')) . '</strong>') ?>
            </p>

            <?php if ($tags === []): ?>
                <p class="field-help"><em><?= e(t('No tags anywhere yet.')) ?></em></p>
            <?php else: ?>
                <ul class="row-list">
                    <?php foreach ($tags as $tag): ?>
                        <li class="row-item">
                            <a class="chip chip--link" href="<?= e(url('/tags/' . rawurlencode($tag['label']))) ?>">
                                <?= e($tag['label']) ?>
                            </a>
                            <div class="row-main">
                                <div class="row-sub">
                                    <?php if ($tag['unused']): ?>
                                        <?= e(t('offered by a layout, used by no entry')) ?>
                                    <?php else: ?>
                                        <strong><?= (int) $tag['entries'] ?></strong>
                                        <?= e(tn((int) $tag['entries'], 'entry', 'entries')) ?>
                                    <?php endif; ?>
                                    · <?= e(implode(', ', $tag['fields'])) ?>
                                    <?php if (count($tag['variants']) > 1): ?>
                                        · <?= e(t('also written')) ?>
                                        <?= e(implode(', ', array_slice($tag['variants'], 1))) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row-actions">
                                <form method="post" action="<?= e(url('/settings/tags/delete')) ?>"
                                      <?php // &#10; is a raw HTML entity, not escaped — intentional. ?>
                                      data-confirm="<?= t('Remove the tag “%s” from %s?&#10;&#10;This cannot be undone.',
                                          e($tag['label']), e(tn((int) $tag['entries'], '%d entry', '%d entries'))) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="tag" value="<?= e($tag['label']) ?>">
                                    <button class="btn btn--sm btn--danger" type="submit"><?= e(t('Delete')) ?></button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
