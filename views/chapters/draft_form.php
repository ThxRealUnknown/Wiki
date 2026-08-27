<?php

use App\ChapterRepo;

/**
 * Write or edit one chapter. Notes never appear in the Story.
 *
 * @var array|null $chapter null when creating
 * @var array      $chapters
 * @var array      $parts   every part label currently in use, for the datalist
 * @var array      $connections
 */

$isNew = $chapter === null;
$action = $isNew ? url('/draft/create') : url('/draft/' . $chapter['slug'] . '/update');
$number = $isNew ? '' : ChapterRepo::formatNumber(
    $chapter['chapter_number'] === null ? null : (float) $chapter['chapter_number']
);
$isVisible = !$isNew && (int) $chapter['is_visible'] === 1;
$startWords = $isNew ? 0 : word_count((string) $chapter['content']);
?>
<div class="columns">
    <?php include APP_ROOT . '/views/partials/chapter_list.php'; ?>

    <div class="content-col">
        <form class="page" method="post" action="<?= e($action) ?>">
            <?= csrf_field() ?>

            <div class="page-head">
                <div class="crumbs">
                    <a href="<?= e(url('/draft')) ?>"><?= e(t('Draft')) ?></a> ›
                    <span><?= $isNew ? e(t('New chapter')) : e($chapter['title']) ?></span>
                </div>

                <input class="input input--title" type="text" name="title" required
                       value="<?= e($isNew ? '' : $chapter['title']) ?>"
                       placeholder="<?= e(t('Chapter title…')) ?>" autocomplete="off"
                       <?= $isNew ? 'autofocus' : '' ?>>

                <div class="chapter-controls">
                    <div class="chapter-control">
                        <label class="field-label" for="chapter-number"><?= e(t('Chapter number')) ?></label>
                        <input class="input" id="chapter-number" type="text" inputmode="decimal"
                               name="chapter_number" value="<?= e($number) ?>" placeholder="1">
                        <p class="field-help"><?= e(t('Decimals work, so 12.5 sits between 12 and 13.')) ?></p>
                    </div>

                    <div class="chapter-control">
                        <label class="field-label" for="chapter-part"><?= e(t('Part')) ?></label>
                        <input class="input" id="chapter-part" type="text" name="part"
                               value="<?= e($isNew ? '' : (string) ($chapter['part'] ?? '')) ?>"
                               placeholder="<?= e(t('e.g. Part One')) ?>" list="chapter-parts" autocomplete="off">
                        <datalist id="chapter-parts">
                            <?php foreach ($parts as $partName): ?>
                                <option value="<?= e($partName) ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <p class="field-help">
                            <?= e(t('Optional. Chapters sharing a part are grouped under it here, in the Story and in the book export.')) ?>
                        </p>
                    </div>

                    <div class="chapter-control">
                        <span class="field-label"><?= e(t('In the story')) ?></span>
                        <label class="switch-row">
                            <input type="checkbox" name="is_visible" value="1"
                                <?= $isVisible ? 'checked' : '' ?>>
                            <span><?= e(t('Show this chapter to readers')) ?></span>
                        </label>
                        <p class="field-help">
                            <?= e(t('Hidden chapters stay here in the Draft and never reach the Story.')) ?>
                        </p>
                    </div>

                    <?php if (!$isNew): ?>
                        <div class="chapter-control chapter-control--meta">
                            <span class="field-label"><?= e(t('Timestamps')) ?></span>
                            <p class="field-help">
                                <?= e(t('Created')) ?> <?= e(human_time($chapter['created_at'])) ?><br>
                                <?= e(t('Last edited %s', human_time($chapter['updated_at']))) ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="field-block">
                <label class="field-label" for="chapter-content">
                    <?= e(t('Content')) ?>
                    <span class="field-help" style="display:inline" data-word-count>
                        <?= e(t($startWords === 1 ? '%s word' : '%s words', number_format($startWords))) ?>
                    </span>
                </label>
                <div class="editor editor--tall" data-editor>
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
                    <div class="editor-surface prose" contenteditable="true" id="chapter-content"
                         data-placeholder="<?= e(t('Write the chapter…')) ?>"><?= $isNew ? '' : $chapter['content'] ?></div>
                    <textarea name="content" hidden data-editor-value><?= e($isNew ? '' : $chapter['content']) ?></textarea>
                </div>
            </div>

            <div class="field-block" style="margin-top:26px">
                <label class="field-label" for="chapter-notes"><?= e(t('Notes')) ?></label>
                <p class="field-help"><?= e(t('Only ever visible here — the Story never shows them.')) ?></p>
                <div class="editor" data-editor>
                    <div class="editor-toolbar">
                        <button type="button" class="editor-btn" data-cmd="bold" title="<?= e(t('Bold')) ?>"><b>B</b></button>
                        <button type="button" class="editor-btn" data-cmd="italic" title="<?= e(t('Italic')) ?>"><i>I</i></button>
                        <button type="button" class="editor-btn" data-cmd="underline" title="<?= e(t('Underline')) ?>"><u>U</u></button>
                        <span class="editor-sep"></span>
                        <button type="button" class="editor-btn" data-block="h3" title="<?= e(t('Subheading')) ?>">H3</button>
                        <button type="button" class="editor-btn" data-block="p" title="<?= e(t('Paragraph')) ?>">¶</button>
                        <button type="button" class="editor-btn" data-cmd="insertUnorderedList" title="<?= e(t('Bullet list')) ?>">•—</button>
                        <button type="button" class="editor-btn" data-cmd="insertOrderedList" title="<?= e(t('Numbered list')) ?>">1.</button>
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
                    <div class="editor-surface prose" contenteditable="true" id="chapter-notes"
                         data-placeholder="<?= e(t('Continuity, reminders, things to fix…')) ?>"><?= $isNew ? '' : $chapter['notes'] ?></div>
                    <textarea name="notes" hidden data-editor-value><?= e($isNew ? '' : $chapter['notes']) ?></textarea>
                </div>
            </div>

            <div class="form-bar">
                <button class="btn btn--primary" type="submit">
                    <?= $isNew ? e(t('Create chapter')) : e(t('Save chapter')) ?>
                </button>
                <a class="btn btn--ghost" href="<?= e(url('/draft')) ?>"><?= e(t('Cancel')) ?></a>
                <span class="spacer"></span>
            </div>
        </form>

        <?php if (!$isNew): ?>
            <div class="page" style="padding-top:0">
                <div class="section" style="margin-top:0">
                    <h2 class="section-title"><?= e(t('This chapter')) ?></h2>
                    <div class="row-item">
                        <div class="row-main">
                            <div class="row-title">
                                <?= $isVisible ? e(t('Shown in the Story')) : e(t('Hidden from the Story')) ?>
                            </div>
                            <div class="row-sub">
                                <?= $isVisible
                                    ? e(t('Readers can see this chapter.'))
                                    : e(t('Only you can see this chapter.')) ?>
                            </div>
                        </div>
                        <div class="row-actions">
                            <form method="post" action="<?= e(url('/draft/' . $chapter['slug'] . '/toggle')) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn--sm" type="submit">
                                    <?= $isVisible ? e(t('Hide from Story')) : e(t('Show in Story')) ?>
                                </button>
                            </form>
                            <?php if ($isVisible): ?>
                                <a class="btn btn--sm btn--ghost"
                                   href="<?= e(url('/story/' . $chapter['slug'])) ?>"><?= e(t('Read it')) ?></a>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/draft/' . $chapter['slug'] . '/delete')) ?>"
                                  data-confirm="<?= e(t('Delete the chapter “%s” for good?', $chapter['title'])) ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn--sm btn--danger" type="submit"><?= e(t('Delete')) ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$isNew): ?>
        <?php
        $railType = 'chapter';
        $railId = (int) $chapter['id'];
        include APP_ROOT . '/views/partials/connections_rail.php';
        ?>
    <?php endif; ?>
</div>
