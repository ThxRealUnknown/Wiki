<?php

namespace App;

/**
 * The world map: multiple layers sharing one coordinate space, so a shape
 * traced on one layer aligns with the others.
 *
 * The coordinate space is fixed at 4000 x 3000 and must not change — every
 * traced shape is stored in those units; re-exporting the artwork at a
 * different size would move every border.
 */
final class WorldMap
{
    /** The coordinate space every traced shape is stored in. */
    public const WIDTH = 4000;
    public const HEIGHT = 3000;

    /** @var array<string, array>|null every layer, keyed by slug, cached for the request */
    private static ?array $cache = null;

    /** Every layer in display order, with the path to its artwork (or null if none uploaded). */
    public static function layers(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $out = [];
        $rows = Database::instance()->all('SELECT * FROM world_maps ORDER BY sort_order, id');

        foreach ($rows as $row) {
            $out[$row['slug']] = [
                'id'    => $row['slug'],
                'label' => $row['label'],
                'image' => $row['image'],
            ];
        }

        return self::$cache = $out;
    }

    public static function layer(string $id): ?array
    {
        return self::layers()[$id] ?? null;
    }

    public static function exists(string $id): bool
    {
        return isset(self::layers()[$id]);
    }

    /** A valid layer id, falling back to the first layer, or '' if there are none. */
    public static function resolve(?string $id): string
    {
        if ($id !== null && self::exists($id)) {
            return $id;
        }

        return array_key_first(self::layers()) ?? '';
    }

    /** True once at least one layer has artwork to show. */
    public static function isDrawn(): bool
    {
        foreach (self::layers() as $layer) {
            if (!empty($layer['image'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds a new, imageless layer at the end of the order, with a slug
     * derived from its label. Give it artwork with setImage().
     */
    public static function create(string $label): array
    {
        $db = Database::instance();
        $slug = self::uniqueSlug($label);
        $sortOrder = (int) $db->value('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM world_maps');

        $db->insert('world_maps', [
            'slug'       => $slug,
            'label'      => $label,
            'image'      => null,
            'sort_order' => $sortOrder,
            'created_at' => now(),
        ]);

        self::$cache = null;

        return self::layer($slug);
    }

    public static function setImage(string $id, ?string $imagePath): bool
    {
        $db = Database::instance();
        $row = $db->first('SELECT id FROM world_maps WHERE slug = :slug', ['slug' => $id]);
        if ($row === null) {
            return false;
        }

        $db->update('world_maps', (int) $row['id'], ['image' => $imagePath]);
        self::$cache = null;

        return true;
    }

    public static function delete(string $id): bool
    {
        $db = Database::instance();
        $row = $db->first('SELECT id FROM world_maps WHERE slug = :slug', ['slug' => $id]);
        if ($row === null) {
            return false;
        }

        $db->delete('world_maps', (int) $row['id']);
        self::$cache = null;

        return true;
    }

    private static function uniqueSlug(string $label): string
    {
        $db = Database::instance();
        $base = slugify($label, 'map');
        $slug = $base;
        $suffix = 2;

        while ((int) $db->value('SELECT COUNT(*) FROM world_maps WHERE slug = :slug', ['slug' => $slug]) > 0) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    /** A stored map area, as {layer, d}. Unparseable input returns null rather than throwing, so a hand-edited backup can't break a page. */
    public static function parseArea(?string $stored): ?array
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return null;
        }

        $decoded = json_decode($stored, true);
        if (!is_array($decoded)) {
            return null;
        }

        $d = trim((string) ($decoded['d'] ?? ''));
        if ($d === '' || !self::isSafePath($d)) {
            return null;
        }

        return [
            'layer' => self::resolve($decoded['layer'] ?? null),
            'd'     => $d,
        ];
    }

    /** A stored map point, as {layer, x, y}. Out-of-bounds coordinates are refused rather than clamped, to surface the mistake. */
    public static function parsePoint(?string $stored): ?array
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return null;
        }

        $decoded = json_decode($stored, true);
        if (!is_array($decoded) || !isset($decoded['x'], $decoded['y'])) {
            return null;
        }

        if (!is_numeric($decoded['x']) || !is_numeric($decoded['y'])) {
            return null;
        }

        $x = (float) $decoded['x'];
        $y = (float) $decoded['y'];

        if ($x < 0 || $y < 0 || $x > self::WIDTH || $y > self::HEIGHT) {
            return null;
        }

        return [
            'layer'  => self::resolve($decoded['layer'] ?? null),
            'x'      => round($x, 1),
            'y'      => round($y, 1),
            'symbol' => self::resolveSymbol($decoded['symbol'] ?? null),
        ];
    }

    public static function encodePoint(string $layer, float $x, float $y, ?string $symbol = null): string
    {
        return json_encode([
            'layer'  => self::resolve($layer),
            'x'      => round($x, 1),
            'y'      => round($y, 1),
            'symbol' => self::resolveSymbol($symbol),
        ], JSON_UNESCAPED_SLASHES);
    }

    /** How wide a window an entry's point cutout shows around it. */
    public const POINT_WINDOW = 900;

    /**
     * The symbols a point may be drawn with. Geometric glyphs, not emoji, so they
     * take the archive's colour instead of fighting it with their own palette.
     */
    private const SYMBOLS = [
        'city'     => ['label' => 'City',      'glyph' => '●'],
        'capital'  => ['label' => 'Capital',   'glyph' => '★'],
        'town'     => ['label' => 'Town',      'glyph' => '◦'],
        'keep'     => ['label' => 'Keep',      'glyph' => '▲'],
        'ruin'     => ['label' => 'Ruin',      'glyph' => '▽'],
        'port'     => ['label' => 'Port',      'glyph' => '⚓'],
        'landmark' => ['label' => 'Landmark',  'glyph' => '◆'],
        'battle'   => ['label' => 'Battle',    'glyph' => '⚔'],
    ];

    public const DEFAULT_SYMBOL = 'city';

    /** @return array<string, array{label:string, glyph:string}> */
    public static function symbols(): array
    {
        return self::SYMBOLS;
    }

    /** A symbol id that certainly exists, falling back to the default. */
    public static function resolveSymbol(?string $id): string
    {
        return $id !== null && isset(self::SYMBOLS[$id]) ? $id : self::DEFAULT_SYMBOL;
    }

    public static function glyph(?string $id): string
    {
        return self::SYMBOLS[self::resolveSymbol($id)]['glyph'];
    }

    public static function encodeArea(string $layer, string $d): string
    {
        return json_encode(
            ['layer' => self::resolve($layer), 'd' => trim($d)],
            JSON_UNESCAPED_SLASHES
        );
    }

    /** A stored map path, as {layer, d}. Same shape as a map area — they differ only in how they're drawn. */
    public static function parsePath(?string $stored): ?array
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return null;
        }

        $decoded = json_decode($stored, true);
        if (!is_array($decoded)) {
            return null;
        }

        $d = trim((string) ($decoded['d'] ?? ''));
        if ($d === '' || !self::isSafePath($d)) {
            return null;
        }

        return [
            'layer' => self::resolve($decoded['layer'] ?? null),
            'd'     => $d,
        ];
    }

    public static function encodePath(string $layer, string $d): string
    {
        return json_encode(
            ['layer' => self::resolve($layer), 'd' => trim($d)],
            JSON_UNESCAPED_SLASHES
        );
    }

    /** Restricts path data to characters SVG path syntax uses, so it can't smuggle markup into the page. */
    public static function isSafePath(string $d): bool
    {
        return (bool) preg_match('/^[MmLlHhVvCcSsQqTtAaZz0-9eE ,.+\-\r\n\t]+$/', $d);
    }
}
