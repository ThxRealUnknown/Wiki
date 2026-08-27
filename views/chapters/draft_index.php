<?php

use App\ChapterRepo;

/**
 * Draft with no chapter selected.
 *
 * @var array  $chapters
 * @var string $goal      target word count for the whole draft, or ''
 */
$shown = 0;
$words = 0;
foreach ($chapters as $item) {
    $shown += (int) $item['is_visible'] === 1 ? 1 : 0;
    $words += word_count((string) $item['content']);
}
$goalWords = $goal === '' ? null : (int) $goal;
$groups = ChapterRepo::grouped($chapters);
?>
<div class="columns">
    <?php include APP_ROOT . '/views/partials/chapter_list.php'; ?>

    <div class="content-col">
        <div class="page">
            <div class="page-head">
                <h1 class="page-title">✍ <?= e(t('Draft')) ?></h1>
                <p class="lede">
                    <?= e(t('Where the story is written.')) ?>
                </p>
                <div class="page-meta">
                    <span><?= e(tn(count($chapters), '%d chapter', '%d chapters')) ?></span>
                    <span>· <?= e(t('%d shown in the Story', $shown)) ?></span>
                    <span>· <?= e(t($words === 1 ? '%s word' : '%s words', number_format($words))) ?></span>
                </div>
                <div class="page-actions">
                    <a class="btn btn--primary" href="<?= e(url('/draft/new')) ?>">＋ <?= e(t('New chapter')) ?></a>
                    <a class="btn" href="<?= e(url('/story')) ?>">📕 <?= e(t('Read the story')) ?></a>
                </div>
            </div>

            <?php if ($goalWords !== null && $goalWords > 0): ?>
                <?php $pct = max(0, min(100, (int) round($words / $goalWords * 100))); ?>
                <div class="progress" style="margin-bottom:22px">
                    <div class="progress-track">
                        <div class="progress-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div class="progress-label">
                        <?= e(t('%s / %s words', number_format($words), number_format($goalWords))) ?>
                        · <?= $pct ?>%
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($chapters === []): ?>
                <div class="empty-state">
                    <span class="empty-state-icon">✍</span>
                    <h3><?= e(t('No chapters yet')) ?></h3>
                    <p>
                        <?= e(t('Connect chapters to any entry — a character, a place, whatever the scene touches.')) ?>
                    </p>
                    <a class="btn btn--primary" href="<?= e(url('/draft/new')) ?>"><?= e(t('Write the first chapter')) ?></a>
                </div>
            <?php else: ?>
                <?php foreach ($groups as $group): ?>
                    <?php if ($group['part'] !== null): ?>
                        <h2 class="section-title"><?= e($group['part']) ?></h2>
                    <?php endif; ?>
                    <ul class="row-list" style="margin-bottom:20px">
                        <?php foreach ($group['chapters'] as $item): ?>
                            <li class="row-item">
                                <div class="row-main">
                                    <div class="row-title">
                                        <?php
                                        $number = App\ChapterRepo::formatNumber(
                                            $item['chapter_number'] === null ? null : (float) $item['chapter_number']
                                        );
                                        ?>
                                        <?php if ($number !== ''): ?>
                                            <span class="chapter-number"><?= e($number) ?></span>
                                        <?php endif; ?>
                                        <?= e($item['title']) ?>
                                        <?php if ((int) $item['is_visible'] === 1): ?>
                                            <span class="badge"><?= e(t('Shown')) ?></span>
                                        <?php else: ?>
                                            <span class="badge badge--muted"><?= e(t('Hidden')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="row-sub">
                                        <?php $itemWords = word_count((string) $item['content']); ?>
                                        <?= e(t($itemWords === 1 ? '%s word' : '%s words', number_format($itemWords))) ?>
                                        · <?= e(t('last edited %s', human_time($item['updated_at']))) ?>
                                    </div>
                                </div>
                                <div class="row-actions">
                                    <a class="btn btn--sm" href="<?= e(url('/draft/' . $item['slug'])) ?>"><?= e(t('Open')) ?></a>
                                    <?php if ((int) $item['is_visible'] === 1): ?>
                                        <a class="btn btn--sm btn--ghost"
                                           href="<?= e(url('/story/' . $item['slug'])) ?>"><?= e(t('Read')) ?></a>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
