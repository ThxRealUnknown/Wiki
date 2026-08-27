<?php

use App\ChapterRepo;

/**
 * One chapter, as a reader sees it — no notes, no edit controls.
 *
 * @var array $chapter
 * @var array $neighbours prev / next within the published sequence
 * @var array $connections
 */

$number = ChapterRepo::formatNumber(
    $chapter['chapter_number'] === null ? null : (float) $chapter['chapter_number']
);
?>
<div class="columns">
    <?php include APP_ROOT . '/views/partials/chapter_list.php'; ?>

    <div class="content-col">
        <div class="page page--reading">
            <div class="page-head">
                <div class="crumbs">
                    <a href="<?= e(url('/story')) ?>"><?= e(t('Story')) ?></a> ›
                    <span><?= e($chapter['title']) ?></span>
                </div>

                <?php if ($number !== ''): ?>
                    <div class="chapter-eyebrow"><?= e(t('Chapter %s', $number)) ?></div>
                <?php endif; ?>

                <h1 class="page-title"><?= e($chapter['title']) ?></h1>
            </div>

            <?php if (trim((string) $chapter['content']) === ''): ?>
                <p class="field-value field-value--empty"><?= e(t('This chapter has no text yet.')) ?></p>
            <?php else: ?>
                <?php // Sanitised on save, so it's safe to print as markup. ?>
                <div class="prose prose--reading"><?= App\EntryLinks::resolve($chapter['content']) ?></div>
            <?php endif; ?>

            <nav class="chapter-nav">
                <?php if ($neighbours['prev'] !== null): ?>
                    <a class="btn btn--ghost" href="<?= e(url('/story/' . $neighbours['prev']['slug'])) ?>">
                        ← <?= e($neighbours['prev']['title']) ?>
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>

                <?php if ($neighbours['next'] !== null): ?>
                    <a class="btn btn--ghost" href="<?= e(url('/story/' . $neighbours['next']['slug'])) ?>">
                        <?= e($neighbours['next']['title']) ?> →
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </div>

    <?php
    $railType = 'chapter';
    $railId = (int) $chapter['id'];
    $railReadOnly = true;
    include APP_ROOT . '/views/partials/connections_rail.php';
    ?>
</div>
