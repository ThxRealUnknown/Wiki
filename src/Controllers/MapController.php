<?php

namespace App\Controllers;

use App\Config;
use App\MapRepo;
use App\Settings;
use App\Uploads;
use App\WorldMap;
use Throwable;

final class MapController
{
    private MapRepo $regions;

    public function __construct()
    {
        $this->regions = new MapRepo();
    }

    /** Feeds the assign picker: /map/lookup?q= — only entries that can hold a shape. */
    public function lookup(): never
    {
        $results = [];

        $kind = match ($_GET['kind'] ?? 'area') {
            'point' => \App\FieldTypes::MAPPOINT,
            'path'  => \App\FieldTypes::MAPPATH,
            default => \App\FieldTypes::MAPAREA,
        };

        foreach ($this->regions->mappable(trim((string) ($_GET['q'] ?? '')), 20, $kind) as $row) {
            $results[] = [
                'id'        => (int) $row['id'],
                'title'     => $row['title'],
                'archive'   => $row['archive'],
                'icon'      => $row['icon'] ?: '•',
                'has_shape' => (bool) $row['has_shape'],
            ];
        }

        json_response(['results' => $results]);
    }

    /** POST /map/assign — puts a freshly traced shape, path or point onto an entry. */
    public function assign(): never
    {
        csrf_verify();

        $entryId = (int) ($_POST['entry'] ?? 0);
        $layer = WorldMap::resolve($_POST['layer'] ?? null);
        $kind = $_POST['kind'] ?? 'area';

        // A point is placed rather than traced, so it takes coordinates
        // instead of path data.
        if ($kind === 'point') {
            if ($entryId <= 0) {
                json_response(['ok' => false, 'error' => t('Nothing to assign.')], 400);
            }

            $placed = $this->regions->assignPoint(
                $entryId,
                $layer,
                (float) ($_POST['x'] ?? -1),
                (float) ($_POST['y'] ?? -1),
                $_POST['symbol'] ?? null
            );

            if (!$placed) {
                json_response([
                    'ok'    => false,
                    'error' => t('That entry has no Map point field, or the spot is off the map.'),
                ], 422);
            }

            json_response(['ok' => true]);
        }

        $path = trim((string) ($_POST['path'] ?? ''));

        if ($entryId <= 0 || $path === '') {
            json_response(['ok' => false, 'error' => t('Nothing to assign.')], 400);
        }

        if (!WorldMap::isSafePath($path)) {
            json_response(['ok' => false, 'error' => t('That is not usable path data.')], 400);
        }

        if ($kind === 'path') {
            if (!$this->regions->assignPath($entryId, $layer, $path)) {
                json_response([
                    'ok'    => false,
                    'error' => t('That entry has no Map path field to put a route in.'),
                ], 422);
            }

            json_response(['ok' => true]);
        }

        if (!$this->regions->assign($entryId, $layer, $path)) {
            json_response([
                'ok'    => false,
                'error' => t('That entry has no Map area field to put a shape in.'),
            ], 422);
        }

        json_response(['ok' => true]);
    }

    /**
     * The world map. ?layer= opens on a given layer, ?focus= highlights an entry
     * (keyed on the entry, not the field, since a field is shared across entries
     * in a layout).
     */
    public function index(): never
    {
        $byLayer = $this->regions->regionsByLayer();
        $pointsByLayer = $this->regions->pointsByLayer();
        $pathsByLayer = $this->regions->pathsByLayer();
        $focus = isset($_GET['focus']) ? (int) $_GET['focus'] : 0;

        // A focused region overrides ?layer= to whichever layer it's actually drawn on.
        $layer = WorldMap::resolve($_GET['layer'] ?? null);

        if ($focus > 0) {
            foreach ([$byLayer, $pointsByLayer, $pathsByLayer] as $set) {
                foreach ($set as $layerId => $items) {
                    foreach ($items as $item) {
                        if ($item['entry_id'] === $focus) {
                            $layer = $layerId;
                            break 3;
                        }
                    }
                }
            }
        }

        render('map/index', [
            'pageTitle' => t('World map'),
            'section'   => 'map',
            'layers'    => WorldMap::layers(),
            'byLayer'   => $byLayer,
            'pointsByLayer' => $pointsByLayer,
            'pathsByLayer' => $pathsByLayer,
            'layer'     => $layer,
            'focus'     => $focus,
            'width'     => WorldMap::WIDTH,
            'height'    => WorldMap::HEIGHT,
            'title'     => Settings::get(Settings::MAP_TITLE, (string) Config::get('site_name', 'World map')),
            'epoch'     => Settings::get(Settings::MAP_EPOCH, ''),
            'drawn'     => WorldMap::isDrawn(),
        ]);
    }

    /** POST /map/layers/create — a new map, named and drawn in one step. */
    public function createLayer(): never
    {
        $label = trim((string) ($_POST['label'] ?? ''));
        if ($label === '') {
            flash(t('A map needs a name.'), 'error');
            redirect('/map');
        }

        $upload = $_FILES['image'] ?? null;
        if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            flash(t('Choose an image for the map first.'), 'error');
            redirect('/map');
        }

        try {
            $path = Uploads::store($upload);
        } catch (Throwable $e) {
            flash($e->getMessage(), 'error');
            redirect('/map');
        }

        $layer = WorldMap::create($label);
        WorldMap::setImage($layer['id'], $path);

        flash(t('Map "%s" added.', $label));
        redirect('/map?layer=' . $layer['id']);
    }

    /** POST /map/layers/delete — removes a map and everything traced on it. */
    public function deleteLayer(): never
    {
        $slug = trim((string) ($_POST['slug'] ?? ''));
        $layer = $slug !== '' ? WorldMap::layer($slug) : null;

        if ($layer === null) {
            flash(t('That map no longer exists.'), 'error');
            redirect('/map');
        }

        $this->regions->purgeLayer($slug);
        Uploads::remove($layer['image']);
        WorldMap::delete($slug);

        flash(t('Map "%s" and everything traced on it was removed.', $layer['label']));
        redirect('/map');
    }

    /** POST /map/info — the page's own title and caption. */
    public function updateInfo(): never
    {
        $title = trim((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            flash(t('The map needs a name.'), 'error');
            redirect('/map');
        }

        Settings::set(Settings::MAP_TITLE, $title);
        Settings::set(Settings::MAP_EPOCH, trim((string) ($_POST['epoch'] ?? '')));

        flash(t('Map details updated.'));
        redirect('/map');
    }
}
