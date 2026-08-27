<?php

use App\ChapterRepo;

/**
 * The reading view's table of contents. Read-only by design.
 *
 * @var array $chapters   published only
 * @var int   $draftCount every chapter, shown or not
 */
?>
<div class="columns">
    <?php include APP_ROOT . '/views/partials/chapter_list.php'; ?>

    <div class="content-col">
        <div class="page">
            <div class="page-head">
                <h1 class="page-title">📕 <?= e(t('Story')) ?></h1>
                <p class="lede">
                    <?= e(t('The Story and its chapters.')) ?>
                </p>
            </div>

            <?php if ($chapters === []): ?>
                <div class="empty-state">
                    <span class="empty-state-icon">📕</span>
                    <h3><?= e(t('Nothing published yet')) ?></h3>
                    <p>
                        <?= $draftCount === 0
                            ? e(t('There are no chapters yet.'))
                            : e(tn($draftCount, '%d chapter is waiting in the Draft, all of them hidden.', '%d chapters are waiting in the Draft, all of them hidden.')) ?>
                    </p>
                    <a class="btn" href="<?= e(url('/draft')) ?>"><?= e(t('Go to the Draft')) ?></a>
                </div>
            <?php else: ?>
                <?php foreach (ChapterRepo::grouped($chapters) as $group): ?>
                    <?php if ($group['part'] !== null): ?>
                        <h2 class="section-title"><?= e($group['part']) ?></h2>
                    <?php endif; ?>
                    <ul class="row-list" style="margin-bottom:20px">
                        <?php foreach ($group['chapters'] as $item): ?>
                            <?php
                            $number = ChapterRepo::formatNumber(
                                $item['chapter_number'] === null ? null : (float) $item['chapter_number']
                            );
                            ?>
                            <li>
                                <a class="row-item" href="<?= e(url('/story/' . $item['slug'])) ?>">
                                    <div class="row-main">
                                        <div class="row-title">
                                            <?php if ($number !== ''): ?>
                                                <span class="chapter-number"><?= e($number) ?></span>
                                            <?php endif; ?>
                                            <?= e($item['title']) ?>
                                        </div>
                                        <div class="row-sub">
                                            <?= e(App\Sanitizer::excerpt((string) $item['content'], 110)) ?>
                                        </div>
                                    </div>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
