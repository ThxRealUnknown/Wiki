<?php

/**
 * One icon field. The hidden input carries the actual value; the button just
 * shows it and opens the picker, which is shared across the page.
 *
 * @var string $iconValue what the archive has now, or '' for none
 * @var string $iconId    unique within the page, for the label to point at
 */
?>
<div class="icon-field" data-icon-field>
    <button type="button" class="icon-trigger" id="<?= e($iconId) ?>-icon"
            data-icon-trigger
            aria-label="<?= $iconValue === ''
                ? e(t('Choose an icon'))
                : e(t('Choose an icon — currently %s', $iconValue)) ?>">
        <span class="icon-trigger-glyph<?= $iconValue === '' ? ' is-empty' : '' ?>" data-icon-shown>
            <?= e($iconValue === '' ? '•' : $iconValue) ?>
        </span>
        <span class="icon-trigger-hint"><?= e(t('Choose…')) ?></span>
    </button>

    <input type="hidden" name="icon" value="<?= e($iconValue) ?>" data-icon-value>
</div>
