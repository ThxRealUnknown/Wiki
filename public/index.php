<?php

/**
 * Front controller. Every request that is not an existing file lands here via
 * .htaccess and is dispatched on its path segments.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Config;
use App\Controllers\ApiController;
use App\Controllers\CategoryController;
use App\Controllers\ChapterController;
use App\Controllers\ConnectionController;
use App\Controllers\EntryController;
use App\Controllers\ExportController;
use App\Controllers\HistoryController;
use App\Controllers\HomeController;
use App\Controllers\MapController;
use App\Controllers\LayoutController;
use App\Controllers\PinboardController;
use App\Controllers\SettingsController;
use App\Controllers\TagController;
use App\Controllers\TimelineController;
use App\Database;

// The app is unusable before bin/install.php has run; say so plainly instead of
// throwing a connection error at the user.
try {
    Database::instance()->tableExists('categories') || throw new RuntimeException('not installed');
} catch (Throwable $e) {
    http_response_code(503);
    require APP_ROOT . '/views/not_installed.php';
    exit;
}

$path = requestPath();
$segments = $path === '' ? [] : explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isPost = $method === 'POST';

if ($isPost) {
    csrf_verify();
}

$home = new HomeController();
$categories = new CategoryController();
$entries = new EntryController();
$layouts = new LayoutController();
$api = new ApiController();
$chapters = new ChapterController();
$connections = new ConnectionController();
$export = new ExportController();
$settings = new SettingsController();
$map = new MapController();
$pinboard = new PinboardController();
$timeline = new TimelineController();
$history = new HistoryController();
$tags = new TagController();

switch ($segments[0] ?? '') {
    case '':
        $home->dashboard();

    case 'search':
        $home->search();

    case 'map':
        // /map                  the map
        // /map/lookup           entries that can hold a shape
        // /map/assign           POST: put a traced shape on an entry
        // /map/layers/create    POST: add a new map, named and drawn
        // /map/layers/delete    POST: remove a map and everything traced on it
        // /map/info             POST: change the page's title and caption
        $second = $segments[1] ?? '';

        if ($isPost) {
            if ($second === 'assign') {
                $map->assign();
            }
            if ($second === 'layers') {
                match ($segments[2] ?? '') {
                    'create' => $map->createLayer(),
                    'delete' => $map->deleteLayer(),
                    default  => abort(404),
                };
            }
            if ($second === 'info') {
                $map->updateInfo();
            }
            abort(404);
        }

        if ($second === 'lookup') {
            $map->lookup();
        }
        if ($second !== '') {
            abort(404);
        }

        $map->index();

    case 'pinboard':
        // /pinboard              the board
        // /pinboard/graph        pins and strings, as JSON
        // /pinboard/connect      POST: tie two entries together
        // /pinboard/disconnect   POST: cut one string
        // /pinboard/note         POST: change a connection's note
        // /pinboard/link         POST: point one entry at another through a field
        // /pinboard/unlink       POST: take that back out again
        $second = $segments[1] ?? '';

        if ($isPost) {
            match ($second) {
                'connect'    => $pinboard->connect(),
                'disconnect' => $pinboard->disconnect(),
                'note'       => $pinboard->note(),
                'link'       => $pinboard->link(),
                'unlink'     => $pinboard->unlink(),
                default      => abort(404),
            };
        }

        if ($second === 'graph') {
            $pinboard->graph();
        }
        if ($second !== '') {
            abort(404);
        }

        $pinboard->index();

    case 'timeline':
        // /timeline            the timeline
        // /timeline/events     every Date/Era value, as JSON
        // /timeline/calendar   the month-grid calendar view
        $second = $segments[1] ?? '';

        if ($second === 'events') {
            $timeline->events();
        }
        if ($second === 'calendar') {
            $timeline->calendar();
        }
        if ($second !== '') {
            abort(404);
        }

        $timeline->index();

    case 'archives':
        // /archives                       manage archives
        // /archives/create                POST
        // /archives/{id}/update|delete    POST
        // /archives/reorder               POST (fetch)
        $second = $segments[1] ?? '';

        if ($second === '' && !$isPost) {
            $categories->index();
        }
        if ($second === 'create' && $isPost) {
            $categories->store();
        }
        if ($second === 'reorder' && $isPost) {
            $categories->reorder();
        }
        if (ctype_digit($second) && $isPost) {
            match ($segments[2] ?? '') {
                'update' => $categories->update((int) $second),
                'delete' => $categories->destroy((int) $second),
                default  => abort(404),
            };
        }
        abort(404);

    case 'api':
        if (($segments[1] ?? '') === 'lookup') {
            $api->lookup();
        }
        abort(404);

    case 'export':
        // /export              the page
        // /export/wiki|book    downloads a readable document
        // /export/backup       downloads the full JSON backup
        // /export/import       POST: preview a restore
        // /export/import/apply POST: write it
        $second = $segments[1] ?? '';

        if ($isPost) {
            if ($second === 'import') {
                ($segments[2] ?? '') === 'apply'
                    ? $export->importApply()
                    : $export->importPreview();
            }
            abort(404);
        }

        match ($second) {
            ''       => $export->index(),
            'wiki'   => $export->wiki(),
            'book'   => $export->book(),
            'backup' => $export->backup(),
            default  => abort(404),
        };

    case 'history':
        // /history              every entry edit/deletion, newest first
        // /history/clear        POST: wipe every recorded revision
        // /history/{id}/restore POST: bring that version back
        // /history/{id}/diff    what that edit actually changed
        $second = $segments[1] ?? '';

        if ($isPost) {
            if ($second === 'clear') {
                $history->clear();
            }
            if (ctype_digit($second) && ($segments[2] ?? '') === 'restore') {
                $history->restore((int) $second);
            }
            abort(404);
        }

        if (ctype_digit($second) && ($segments[2] ?? '') === 'diff') {
            $history->diff((int) $second);
        }

        if ($second !== '') {
            abort(404);
        }

        $history->index();

    case 'tags':
        // /tags/{tag} — every entry carrying that tag
        $second = $segments[1] ?? '';

        if ($isPost || $second === '') {
            abort(404);
        }

        $tags->show($second);

    case 'settings':
        $second = $segments[1] ?? '';

        if ($second === '' && !$isPost) {
            $settings->index();
        }
        if ($second === 'banner' && $isPost) {
            $settings->updateBanner();
        }
        if ($second === 'features' && $isPost) {
            $settings->updateFeatures();
        }
        if ($second === 'language' && $isPost) {
            $settings->updateLanguage();
        }
        if ($second === 'timeline' && $isPost) {
            $settings->updateTimeline();
        }
        if ($second === 'goal' && $isPost) {
            $settings->updateGoal();
        }
        if ($second === 'calendar') {
            if ($isPost) {
                $settings->updateCalendar();
            }
            $settings->calendar();
        }
        if ($second === 'tags' && ($segments[2] ?? '') === 'delete' && $isPost) {
            $settings->deleteTag();
        }
        abort(404);

    case 'connections':
        // /connections/create           POST
        // /connections/{id}/update      POST
        // /connections/{id}/delete      POST
        $second = $segments[1] ?? '';

        if ($second === 'create' && $isPost) {
            $connections->store();
        }
        if (ctype_digit($second) && $isPost) {
            match ($segments[2] ?? '') {
                'update' => $connections->update((int) $second),
                'delete' => $connections->destroy((int) $second),
                default  => abort(404),
            };
        }
        abort(404);

    case 'draft':
        // /draft                        chapter list
        // /draft/new                    blank chapter
        // /draft/create                 POST
        // /draft/{slug}                 write / edit
        // /draft/{slug}/update|toggle|delete   POST
        $second = $segments[1] ?? '';

        if ($second === '' && !$isPost) {
            $chapters->draftIndex();
        }
        if ($second === 'new' && !$isPost) {
            $chapters->draftCreate();
        }
        if ($second === 'create' && $isPost) {
            $chapters->draftStore();
        }
        if ($second !== '') {
            $action = $segments[2] ?? '';
            if ($action === '' && !$isPost) {
                $chapters->draftShow($second);
            }
            if ($isPost) {
                match ($action) {
                    'update' => $chapters->draftUpdate($second),
                    'toggle' => $chapters->draftToggle($second),
                    'delete' => $chapters->draftDestroy($second),
                    default  => abort(404),
                };
            }
        }
        abort(404);

    case 'story':
        $second = $segments[1] ?? '';

        if ($isPost) {
            abort(404);
        }
        if ($second === '') {
            $chapters->storyIndex();
        }
        $chapters->storyShow($second);

    case 'c':
        $categorySlug = $segments[1] ?? '';
        if ($categorySlug === '') {
            abort(404);
        }

        $second = $segments[2] ?? '';

        // ---- entries --------------------------------------------------
        if ($second === '') {
            $entries->index($categorySlug);
        }
        if ($second === 'new' && !$isPost) {
            $entries->create($categorySlug);
        }
        if ($second === 'create' && $isPost) {
            $entries->store($categorySlug);
        }
        if ($second === 'archived' && !$isPost) {
            $entries->archived($categorySlug);
        }
        if ($second === 'e') {
            $entrySlug = $segments[3] ?? '';
            if ($entrySlug === '') {
                abort(404);
            }

            $action = $segments[4] ?? '';
            if ($action === '' && !$isPost) {
                $entries->show($categorySlug, $entrySlug);
            }
            if ($action === 'edit' && !$isPost) {
                $entries->edit($categorySlug, $entrySlug);
            }
            if ($action === 'update' && $isPost) {
                $entries->update($categorySlug, $entrySlug);
            }
            if ($action === 'delete' && $isPost) {
                $entries->destroy($categorySlug, $entrySlug);
            }
            if ($action === 'duplicate' && $isPost) {
                $entries->duplicate($categorySlug, $entrySlug);
            }
            if ($action === 'favorite' && $isPost) {
                $entries->favorite($categorySlug, $entrySlug);
            }
            if ($action === 'archive' && $isPost) {
                $entries->archive($categorySlug, $entrySlug);
            }
            if ($action === 'restore' && $isPost) {
                $entries->restore($categorySlug, $entrySlug);
            }
            abort(404);
        }

        // ---- layouts --------------------------------------------------
        if ($second === 'layouts') {
            $third = $segments[3] ?? '';

            if ($third === '' && !$isPost) {
                $layouts->index($categorySlug);
            }
            if ($third === 'create' && $isPost) {
                $layouts->store($categorySlug);
            }
            if (ctype_digit($third)) {
                $layoutId = (int) $third;
                $action = $segments[4] ?? '';

                if ($action === '' && !$isPost) {
                    $layouts->edit($categorySlug, $layoutId);
                }

                // /c/{cat}/layouts/{id}/fields[/{fieldId}/restore|destroy]
                if ($action === 'fields') {
                    $fieldId = $segments[5] ?? '';

                    if ($fieldId === '' && !$isPost) {
                        $layouts->fields($categorySlug, $layoutId);
                    }
                    if (ctype_digit($fieldId) && $isPost) {
                        match ($segments[6] ?? '') {
                            'restore' => $layouts->restoreField($categorySlug, $layoutId, (int) $fieldId),
                            'destroy' => $layouts->destroyField($categorySlug, $layoutId, (int) $fieldId),
                            default   => abort(404),
                        };
                    }
                    abort(404);
                }

                if ($isPost) {
                    match ($action) {
                        'update'    => $layouts->update($categorySlug, $layoutId),
                        'delete'    => $layouts->destroy($categorySlug, $layoutId),
                        'duplicate' => $layouts->duplicate($categorySlug, $layoutId),
                        'default'   => $layouts->makeDefault($categorySlug, $layoutId),
                        default     => abort(404),
                    };
                }
            }
            abort(404);
        }

        abort(404);

    default:
        abort(404);
}

/**
 * The path the request is asking for, relative to the app's base path and with
 * no leading or trailing slash.
 */
function requestPath(): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $uri = (string) parse_url($uri, PHP_URL_PATH);
    $uri = rawurldecode($uri);

    $base = base_path();
    if ($base !== '' && str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
    }

    return trim($uri, '/');
}
