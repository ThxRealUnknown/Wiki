<?php

use App\ChapterRepo;

/**
 * The middle column for Draft and Story.
 *
 * @var array      $chapters
 * @var array|null $activeChapter
 * @var string     $section 'draft' or 'story'
 */

$activeChapter = $activeChapter ?? null;
$isDraft = ($section ?? 'draft') === 'draft';
$base = $isDraft ? '/draft/' : '/story/';
?>
<div class="list-col">
    <div class="list-head">
        <div class="list-title">
            <span class="archive-icon" style="--archive-color: <?= $isDraft ? '#c98a4b' : '#7b6cd9' ?>">
                <?= $isDraft ? '✍' : '📕' ?>
            </span>
            <?= $isDraft ? e(t('Draft')) : e(t('Story')) ?>
        </div>
        <div class="list-sub">
            <?= e(tn(count($chapters), '%d chapter', '%d chapters')) ?>
            <?= $isDraft ? '' : ' ' . e(t('published')) ?>
        </div>

        <?php if ($isDraft): ?>
            <div class="list-actions">
                <a class="btn btn--primary btn--sm" href="<?= e(url('/draft/new')) ?>">＋ <?= e(t('New chapter')) ?></a>
                <a class="btn btn--ghost btn--sm" href="<?= e(url('/story')) ?>">📕 <?= e(t('Read')) ?></a>
            </div>
        <?php else: ?>
            <?php
            // Goes to the chapter's draft if one is open, else the draft overview.
            $backToDraft = !empty($activeChapter['slug'])
                ? '/draft/' . $activeChapter['slug']
                : '/draft';
            ?>
            <div class="list-actions">
                <a class="btn btn--ghost btn--sm" href="<?= e(url($backToDraft)) ?>">✍ <?= e(t('Back to draft')) ?></a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($chapters === []): ?>
        <div class="list-empty">
            <?php if ($isDraft): ?>
                <?php // the translated value embeds its own <br> — not escaped, intentional. ?>
                <?= t('No chapters yet.<br>Write the first one.') ?>
            <?php else: ?>
                <?= t('No chapters are shown yet.<br>Flag one as shown in the Draft.') ?>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <ul class="entry-list">
            <?php foreach ($chapters as $item): ?>
                <?php
                $isActive = $activeChapter !== null && (int) $activeChapter['id'] === (int) $item['id'];
                $rowNumber = ChapterRepo::formatNumber(
                    $item['chapter_number'] === null ? null : (float) $item['chapter_number']
                );
                ?>
                <li>
                    <a class="entry-item<?= $isActive ? ' is-active' : '' ?>"
                       href="<?= e(url($base . $item['slug'])) ?>">
                        <div class="entry-item-title">
                            <?php if ($rowNumber !== ''): ?>
                                <span class="chapter-number"><?= e($rowNumber) ?></span>
                            <?php endif; ?>
                            <?= e($item['title']) ?>
                            <?php if ($isDraft && (int) $item['is_visible'] === 1): ?>
                                <span class="dot-shown" title="<?= e(t('Shown in the Story')) ?>">●</span>
                            <?php endif; ?>
                        </div>
                        <div class="entry-item-meta">
                            <?php if ($isDraft): ?>
                                <?= e(t('Created')) ?> <?= e(human_time($item['created_at'])) ?>
                                · <?= e(t('edited')) ?> <?= e(human_time($item['updated_at'])) ?>
                            <?php else: ?>
                                <?= e(human_time($item['updated_at'])) ?>
                            <?php endif; ?>
                        </div>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
