<?php

namespace App;

/**
 * Reads the traced regions off the entries that carry a Map area field.
 * A region lives in the entry's field value, the same as any other field;
 * this gathers them across the whole archive so the map page can draw them
 * all at once.
 */
final class MapRepo
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * Every traced region, grouped by the layer it sits on and carrying enough
     * of its entry to label it and link to it. Regions that fail to parse are
     * dropped rather than drawn.
     */
    public function regionsByLayer(): array
    {
        $out = [];
        foreach (array_keys(WorldMap::layers()) as $layerId) {
            $out[$layerId] = [];
        }

        $rows = $this->db->all(
            "SELECT ev.value_text,
                    lf.id   AS field_id,
                    lf.label AS field_label,
                    e.id    AS entry_id,
                    e.title,
                    e.slug,
                    c.name  AS archive,
                    c.slug  AS archive_slug,
                    c.icon,
                    c.color
               FROM entry_values ev
               JOIN layout_fields lf ON lf.id = ev.field_id
                                    AND lf.field_type = :type
                                    AND lf.archived_at IS NULL
               JOIN entries e ON e.id = ev.entry_id
               JOIN categories c ON c.id = e.category_id
              WHERE TRIM(COALESCE(ev.value_text, '')) <> ''
              ORDER BY c.sort_order, e.title",
            ['type' => FieldTypes::MAPAREA]
        );

        foreach ($rows as $row) {
            $area = WorldMap::parseArea($row['value_text']);
            if ($area === null) {
                continue;
            }

            $out[$area['layer']][] = [
                'field_id' => (int) $row['field_id'],
                'entry_id' => (int) $row['entry_id'],
                'title'    => $row['title'],
                'archive'  => $row['archive'],
                'icon'     => $row['icon'],
                'color'    => $row['color'],
                'url'      => '/c/' . $row['archive_slug'] . '/e/' . $row['slug'],
                'd'        => $area['d'],
            ];
        }

        return $out;
    }

    /** Entries whose layout carries a Map area field — the only ones that can hold a shape. */
    public function mappable(string $query = '', int $limit = 20, string $type = FieldTypes::MAPAREA): array
    {
        $sql = "SELECT DISTINCT e.id, e.title, c.name AS archive, c.icon,
                       lf.id AS field_id
                  FROM entries e
                  JOIN categories c ON c.id = e.category_id
                  JOIN layout_fields lf ON lf.layout_id = e.layout_id
                                       AND lf.field_type = :type
                                       AND lf.archived_at IS NULL
                 WHERE e.archived_at IS NULL";

        $params = ['type' => $type];

        if ($query !== '') {
            $sql .= ' AND e.title LIKE :q';
            $params['q'] = '%' . $query . '%';
        }

        $sql .= ' ORDER BY e.title LIMIT ' . max(1, $limit);

        $rows = $this->db->all($sql, $params);

        // So the picker can warn before replacing an existing shape.
        foreach ($rows as $i => $row) {
            $existing = $this->db->first(
                'SELECT value_text FROM entry_values WHERE entry_id = :e AND field_id = :f',
                ['e' => (int) $row['id'], 'f' => (int) $row['field_id']]
            );
            $rows[$i]['has_shape'] = $existing !== null
                && trim((string) $existing['value_text']) !== '';
        }

        return $rows;
    }

    /** The Map area field on an entry's layout, or null if it has none. */
    public function fieldFor(int $entryId, string $type = FieldTypes::MAPAREA): ?int
    {
        $row = $this->db->first(
            'SELECT lf.id
               FROM entries e
               JOIN layout_fields lf ON lf.layout_id = e.layout_id
                                    AND lf.field_type = :type
                                    AND lf.archived_at IS NULL
              WHERE e.id = :id
              ORDER BY lf.sort_order
              LIMIT 1',
            ['id' => $entryId, 'type' => $type]
        );

        return $row === null ? null : (int) $row['id'];
    }

    /**
     * Writes a traced shape onto an entry. Returns false when the entry has
     * nowhere to put one, or the shape is not usable path data.
     */
    public function assign(int $entryId, string $layer, string $path): bool
    {
        $fieldId = $this->fieldFor($entryId);
        if ($fieldId === null || !WorldMap::isSafePath($path) || trim($path) === '') {
            return false;
        }

        $value = WorldMap::encodeArea($layer, $path);
        $existing = $this->db->first(
            'SELECT id FROM entry_values WHERE entry_id = :e AND field_id = :f',
            ['e' => $entryId, 'f' => $fieldId]
        );

        if ($existing === null) {
            $this->db->insert('entry_values', [
                'entry_id'   => $entryId,
                'field_id'   => $fieldId,
                'value_text' => $value,
            ]);
        } else {
            $this->db->update('entry_values', (int) $existing['id'], ['value_text' => $value]);
        }

        return true;
    }

    /**
     * Every placed point, grouped by layer. Same shape as regionsByLayer so the
     * map view can treat the two the same way.
     */
    public function pointsByLayer(): array
    {
        $out = [];
        foreach (array_keys(WorldMap::layers()) as $layerId) {
            $out[$layerId] = [];
        }

        $rows = $this->db->all(
            "SELECT ev.value_text,
                    lf.id   AS field_id,
                    e.id    AS entry_id,
                    e.title,
                    e.slug,
                    c.name  AS archive,
                    c.slug  AS archive_slug,
                    c.icon,
                    c.color
               FROM entry_values ev
               JOIN layout_fields lf ON lf.id = ev.field_id
                                    AND lf.field_type = :type
                                    AND lf.archived_at IS NULL
               JOIN entries e ON e.id = ev.entry_id
               JOIN categories c ON c.id = e.category_id
              WHERE TRIM(COALESCE(ev.value_text, '')) <> ''
              ORDER BY c.sort_order, e.title",
            ['type' => FieldTypes::MAPPOINT]
        );

        foreach ($rows as $row) {
            $point = WorldMap::parsePoint($row['value_text']);
            if ($point === null) {
                continue;
            }

            $out[$point['layer']][] = [
                'field_id' => (int) $row['field_id'],
                'entry_id' => (int) $row['entry_id'],
                'title'    => $row['title'],
                'archive'  => $row['archive'],
                'icon'     => $row['icon'],
                'color'    => $row['color'],
                'url'      => '/c/' . $row['archive_slug'] . '/e/' . $row['slug'],
                'x'        => $point['x'],
                'y'        => $point['y'],
                'symbol'   => $point['symbol'],
                'glyph'    => WorldMap::glyph($point['symbol']),
            ];
        }

        return $out;
    }

    /**
     * Drops a point onto an entry. Returns false when the entry has no Map
     * point field, or the coordinates fall outside the map.
     */
    public function assignPoint(int $entryId, string $layer, float $x, float $y, ?string $symbol = null): bool
    {
        $fieldId = $this->fieldFor($entryId, FieldTypes::MAPPOINT);
        if ($fieldId === null) {
            return false;
        }

        $value = WorldMap::encodePoint($layer, $x, $y, $symbol);
        if (WorldMap::parsePoint($value) === null) {
            return false;              // off the map
        }

        $existing = $this->db->first(
            'SELECT id FROM entry_values WHERE entry_id = :e AND field_id = :f',
            ['e' => $entryId, 'f' => $fieldId]
        );

        if ($existing === null) {
            $this->db->insert('entry_values', [
                'entry_id'   => $entryId,
                'field_id'   => $fieldId,
                'value_text' => $value,
            ]);
        } else {
            $this->db->update('entry_values', (int) $existing['id'], ['value_text' => $value]);
        }

        return true;
    }

    /**
     * Every traced path, grouped by layer. Same shape as regionsByLayer so the
     * map view can treat the three the same way.
     */
    public function pathsByLayer(): array
    {
        $out = [];
        foreach (array_keys(WorldMap::layers()) as $layerId) {
            $out[$layerId] = [];
        }

        $rows = $this->db->all(
            "SELECT ev.value_text,
                    lf.id   AS field_id,
                    e.id    AS entry_id,
                    e.title,
                    e.slug,
                    c.name  AS archive,
                    c.slug  AS archive_slug,
                    c.icon,
                    c.color
               FROM entry_values ev
               JOIN layout_fields lf ON lf.id = ev.field_id
                                    AND lf.field_type = :type
                                    AND lf.archived_at IS NULL
               JOIN entries e ON e.id = ev.entry_id
               JOIN categories c ON c.id = e.category_id
              WHERE TRIM(COALESCE(ev.value_text, '')) <> ''
              ORDER BY c.sort_order, e.title",
            ['type' => FieldTypes::MAPPATH]
        );

        foreach ($rows as $row) {
            $path = WorldMap::parsePath($row['value_text']);
            if ($path === null) {
                continue;
            }

            $out[$path['layer']][] = [
                'field_id' => (int) $row['field_id'],
                'entry_id' => (int) $row['entry_id'],
                'title'    => $row['title'],
                'archive'  => $row['archive'],
                'icon'     => $row['icon'],
                'color'    => $row['color'],
                'url'      => '/c/' . $row['archive_slug'] . '/e/' . $row['slug'],
                'd'        => $path['d'],
            ];
        }

        return $out;
    }

    /**
     * Writes a traced path onto an entry. Returns false when the entry has
     * nowhere to put one, or the path is not usable path data.
     */
    public function assignPath(int $entryId, string $layer, string $path): bool
    {
        $fieldId = $this->fieldFor($entryId, FieldTypes::MAPPATH);
        if ($fieldId === null || !WorldMap::isSafePath($path) || trim($path) === '') {
            return false;
        }

        $value = WorldMap::encodePath($layer, $path);
        $existing = $this->db->first(
            'SELECT id FROM entry_values WHERE entry_id = :e AND field_id = :f',
            ['e' => $entryId, 'f' => $fieldId]
        );

        if ($existing === null) {
            $this->db->insert('entry_values', [
                'entry_id'   => $entryId,
                'field_id'   => $fieldId,
                'value_text' => $value,
            ]);
        } else {
            $this->db->update('entry_values', (int) $existing['id'], ['value_text' => $value]);
        }

        return true;
    }

    /**
     * Clears every shape and point stored on a layer that is about to be
     * deleted. Reads the raw "layer" key rather than WorldMap::parseArea()/
     * parsePoint() (which fall back to another layer), so this is correct
     * whether it runs before or after the layer itself is removed.
     */
    public function purgeLayer(string $layerId): int
    {
        $cleared = 0;

        foreach ([FieldTypes::MAPAREA, FieldTypes::MAPPOINT, FieldTypes::MAPPATH] as $type) {
            $rows = $this->db->all(
                "SELECT ev.id, ev.value_text
                   FROM entry_values ev
                   JOIN layout_fields lf ON lf.id = ev.field_id AND lf.field_type = :type
                  WHERE TRIM(COALESCE(ev.value_text, '')) <> ''",
                ['type' => $type]
            );

            foreach ($rows as $row) {
                $decoded = json_decode((string) $row['value_text'], true);
                if (is_array($decoded) && ($decoded['layer'] ?? null) === $layerId) {
                    $this->db->update('entry_values', (int) $row['id'], ['value_text' => null]);
                    $cleared++;
                }
            }
        }

        return $cleared;
    }

    /** How many regions are traced in total, for the empty state. */
    public function count(): int
    {
        $total = 0;
        foreach ($this->regionsByLayer() as $regions) {
            $total += count($regions);
        }

        return $total;
    }
}
