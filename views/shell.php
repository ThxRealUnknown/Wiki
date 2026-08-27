<?php

/**
 * The page frame: banner, sidebar, workspace. Every render() passes through
 * here, so this file is the only place the chrome is described.
 *
 * @var string      $content     the rendered view
 * @var string|null $pageTitle
 * @var string|null $section     'draft' or 'story' for the book links
 * @var array|null  $category    the open archive, when there is one
 * @var array|null  $activeChapter
 */

use App\CategoryRepo;
use App\Language;
use App\Settings;

$categories = (new CategoryRepo())->treeWithCounts();
$banner = Settings::get(Settings::SITE_BANNER);
$openId = isset($category['id']) ? (int) $category['id'] : 0;

// Every ancestor of the open archive stays lit, so a nested archive never looks
// orphaned in the tree.
$openTrail = [];
if ($openId > 0) {
    $trail = static function (array $nodes, array $path) use (&$trail, $openId, &$openTrail): bool {
        foreach ($nodes as $node) {
            $here = array_merge($path, [(int) $node['id']]);
            if ((int) $node['id'] === $openId || $trail($node['children'] ?? [], $here)) {
                $openTrail = array_merge($openTrail, $here);

                return true;
            }
        }

        return false;
    };
    $trail($categories, []);
}
$openTrail = array_flip($openTrail);

/** One archive and everything filed under it. */
$renderArchive = static function (array $node, int $depth) use (&$renderArchive, $openId, $openTrail): string {
    $isOpen = (int) $node['id'] === $openId;
    $isLit = isset($openTrail[(int) $node['id']]);
    $children = $node['children'] ?? [];

    $classes = 'archive-link';
    if ($depth > 0) {
        $classes .= ' archive-link--child';
    }
    if ($isOpen) {
        $classes .= ' is-active';
    } elseif ($isLit) {
        $classes .= ' is-open';
    }

    $html = '<li>'
        . '<div class="archive-row">'
        . ($children !== []
            ? '<button type="button" class="archive-toggle" data-archive-toggle="' . (int) $node['id'] . '"'
                . ' data-archive-name="' . e($node['name']) . '"'
                . ' aria-expanded="true" aria-label="' . e(t('Collapse %s', $node['name'])) . '">▾</button>'
            : '<span class="archive-toggle archive-toggle--spacer"></span>')
        . '<a class="' . $classes . '"'
        . ' style="--archive-color: ' . e($node['color'] ?: 'var(--accent)') . '"'
        . ' href="' . e(url('/c/' . $node['slug'])) . '">'
        . '<span class="archive-icon">' . e($node['icon'] ?: '•') . '</span>'
        . '<span class="archive-name">' . e($node['name']) . '</span>'
        . '<span class="archive-count">' . (int) $node['entry_count'] . '</span>'
        . '</a>'
        . '</div>';

    if ($children !== []) {
        $html .= '<ul class="archive-list archive-list--children" data-archive-children="' . (int) $node['id'] . '">';
        foreach ($children as $child) {
            $html .= $renderArchive($child, $depth + 1);
        }
        $html .= '</ul>';
    }

    return $html . '</li>';
};

// Story → Draft lands on the chapter being read, or the overview if none is open.
$draftHref = '/draft';
if (($section ?? '') === 'story' && !empty($activeChapter['slug'])) {
    $draftHref = '/draft/' . $activeChapter['slug'];
}

$chapters = new App\ChapterRepo();
?>
<!DOCTYPE html>
<html lang="<?= e(Language::locale()) ?>" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Worldbuilder') ?> · Worldbuilder</title>
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <?php // Where the site is served from, for the scripts that call it back. ?>
    <meta name="app-base" content="<?= e(rtrim(url('/'), '/')) ?>">
    <link rel="stylesheet" href="<?= e(asset('assets/css/app.css')) ?>">
    <?php // The current locale's whole catalog, once per page, for app.js's own t(). ?>
    <script type="application/json" data-i18n><?= json_encode(Language::forJs(), JSON_UNESCAPED_UNICODE) ?></script>
    <script>
        // Applied before first paint so the page never flashes the wrong theme.
        try {
            var saved = localStorage.getItem('wb-theme');
            if (saved) { document.documentElement.dataset.theme = saved; }
        } catch (e) { /* private mode */ }
    </script>
</head>
<body>
<div class="app">
    <?php if ($banner): ?>
        <div class="site-banner"><img src="<?= e(asset($banner)) ?>" alt=""></div>
    <?php endif; ?>

    <div class="app-body">
        <aside class="sidebar">
            <a class="brand" href="<?= e(url('/')) ?>">
                <span class="brand-mark">◆</span> Worldbuilder
            </a>

            <form class="sidebar-search" method="get" action="<?= e(url('/search')) ?>">
                <input type="search" name="q" placeholder="<?= e(t('Search everything…')) ?>"
                       aria-label="<?= e(t('Search all archives')) ?>" value="<?= e($_GET['q'] ?? '') ?>">
            </form>

            <nav class="sidebar-nav">
                <a class="sidebar-link<?= ($section ?? '') === 'overview' ? ' is-active' : '' ?>"
                   href="<?= e(url('/')) ?>">
                    <span class="sidebar-icon">◈</span> <?= e(t('Overview')) ?>
                </a>
                <?php if (Settings::flag(Settings::FEATURE_MAP)): ?>
                    <a class="sidebar-link<?= ($section ?? '') === 'map' ? ' is-active' : '' ?>"
                       href="<?= e(url('/map')) ?>">
                        <span class="sidebar-icon">🗺</span> <?= e(t('World map')) ?>
                    </a>
                <?php endif; ?>
                <?php if (Settings::flag(Settings::FEATURE_CONNECTIONS)): ?>
                    <a class="sidebar-link<?= ($section ?? '') === 'pinboard' ? ' is-active' : '' ?>"
                       href="<?= e(url('/pinboard')) ?>">
                        <span class="sidebar-icon">⁂</span> <?= e(t('Pinboard')) ?>
                    </a>
                <?php endif; ?>
                <?php if (Settings::flag(Settings::FEATURE_TIMELINE)): ?>
                    <a class="sidebar-link<?= ($section ?? '') === 'timeline' ? ' is-active' : '' ?>"
                       href="<?= e(url('/timeline/calendar')) ?>">
                        <span class="sidebar-icon">📅</span> <?= e(t('Calendar')) ?>
                    </a>
                <?php endif; ?>
            </nav>

            <?php if (Settings::flag(Settings::FEATURE_BOOK)): ?>
                <?php // The book itself, kept clearly apart from the reference archives. ?>
                <div class="sidebar-section sidebar-section--tight">
                    <h2 class="sidebar-heading"><?= e(t('The book')) ?></h2>
                    <a class="sidebar-link<?= ($section ?? '') === 'draft' ? ' is-active' : '' ?>"
                       href="<?= e(url($draftHref)) ?>">
                        <span class="sidebar-icon">✍</span> <?= e(t('Draft')) ?>
                        <span class="archive-count"><?= $chapters->countAll() ?></span>
                    </a>
                    <a class="sidebar-link<?= ($section ?? '') === 'story' ? ' is-active' : '' ?>"
                       href="<?= e(url('/story')) ?>">
                        <span class="sidebar-icon">📕</span> <?= e(t('Story')) ?>
                        <span class="archive-count"><?= $chapters->countVisible() ?></span>
                    </a>
                </div>
            <?php endif; ?>


            <div class="sidebar-section" data-archives-panel>
                <div class="sidebar-heading-row">
                    <button type="button" class="archive-toggle" data-archives-toggle
                            aria-expanded="true" aria-label="<?= e(t('Collapse Archives')) ?>">▾</button>
                    <h2 class="sidebar-heading"><?= e(t('Archives')) ?></h2>
                </div>

                <div data-archives-body>
                    <?php if ($categories === []): ?>
                        <div class="sidebar-empty"><?= e(t('No archives yet.')) ?></div>
                    <?php else: ?>
                        <ul class="archive-list">
                            <?php foreach ($categories as $node): ?>
                                <?= $renderArchive($node, 0) ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar-section sidebar-foot">
                <a class="sidebar-link sidebar-link--muted" href="<?= e(url('/archives')) ?>">
                    <span class="sidebar-icon">⚙</span> <?= e(t('Manage archives')) ?>
                </a>
                <a class="sidebar-link sidebar-link--muted" href="<?= e(url('/export')) ?>">
                    <span class="sidebar-icon">⇩</span> <?= e(t('Export')) ?>
                </a>
                <a class="sidebar-link sidebar-link--muted" href="<?= e(url('/settings')) ?>">
                    <span class="sidebar-icon">⚒</span> <?= e(t('Settings')) ?>
                </a>
                <a class="sidebar-link sidebar-link--muted" href="<?= e(url('/history')) ?>">
                    <span class="sidebar-icon">🕓</span> <?= e(t('Change history')) ?>
                </a>
                <button type="button" class="theme-toggle" data-theme-toggle>
                    <span class="theme-toggle-dark">☾ <?= e(t('Dark')) ?></span>
                    <span class="theme-toggle-light">☀ <?= e(t('Light')) ?></span>
                </button>
            </div>
        </aside>

        <main class="workspace">
            <?= $content ?>
        </main>
    </div>

    <?php // Folds out over the right-hand rail; on every page, by design. ?>
    <?php include APP_ROOT . '/views/partials/favorites_panel.php'; ?>
</div>

<?php $flashes = take_flashes(); ?>
<?php if ($flashes !== []): ?>
    <div class="flash-stack">
        <?php foreach ($flashes as $flash): ?>
            <div class="flash<?= ($flash['type'] ?? '') === 'error' ? ' flash--error' : '' ?>">
                <span><?= e($flash['message'] ?? '') ?></span>
                <button type="button" class="flash-close" data-dismiss aria-label="<?= e(t('Dismiss')) ?>">✕</button>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script src="<?= e(asset('assets/js/app.js')) ?>"></script>
</body>
</html>
