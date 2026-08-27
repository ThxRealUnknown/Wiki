<?php

/**
 * The icon chooser, rendered once for the whole page and shared by every
 * icon field. A dialog, not a panel, so it stacks above dialogs that open it.
 */
?>
<dialog id="icon-picker" class="icon-dialog" data-icon-picker>
    <div class="dialog-body">
        <h2 class="dialog-title"><?= e(t('Choose an icon')) ?></h2>
        <p class="dialog-sub">
            <?= e(t('%d to choose from — or paste anything else at the bottom.', App\Icons::count())) ?>
        </p>

        <input class="input icon-search" type="search" autocomplete="off"
               placeholder="<?= e(t('Search icons — castle, war, faith…')) ?>"
               aria-label="<?= e(t('Search icons')) ?>" data-icon-search>

        <div class="icon-groups" data-icon-groups>
            <?php foreach (App\Icons::groups() as $group => $icons): ?>
                <section class="icon-group" data-icon-group>
                    <h3 class="icon-group-title"><?= e($group) ?></h3>
                    <div class="icon-grid">
                        <?php foreach ($icons as $glyph => $keywords): ?>
                            <button type="button" class="icon-option"
                                    data-icon="<?= e($glyph) ?>"
                                    data-keywords="<?= e($keywords) ?>"
                                    title="<?= e($keywords) ?>"><?= e($glyph) ?></button>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>

            <p class="icon-empty" data-icon-empty hidden>
                <?= e(t('Nothing matches. Paste the one you want below.')) ?>
            </p>
        </div>

        <div class="icon-custom">
            <label for="icon-custom-value"><?= e(t('Or paste one')) ?></label>
            <?php // maxlength 4 covers an emoji plus variation selector, matching the 2-char DB limit. ?>
            <input class="input" id="icon-custom-value" type="text" maxlength="4"
                   autocomplete="off" data-icon-custom>
            <button type="button" class="btn btn--sm" data-icon-custom-use><?= e(t('Use it')) ?></button>
            <span class="icon-custom-spacer"></span>
            <button type="button" class="btn btn--ghost btn--sm" data-icon-clear><?= e(t('No icon')) ?></button>
        </div>
    </div>

    <div class="dialog-foot">
        <button class="btn btn--ghost" type="button" data-close-dialog><?= e(t('Cancel')) ?></button>
    </div>
</dialog>
