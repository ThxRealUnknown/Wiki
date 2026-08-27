<?php

namespace App;

/**
 * Reads every Date and Era field value off entries across every archive, for
 * the timeline page — the same way MapRepo gathers traced regions.
 */
final class TimelineRepo
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    /**
     * @return array{
     *     points: array<int, array<string, mixed>>,
     *     eras: array<int, array<string, mixed>>
     * }
     */
    public function events(): array
    {
        return [
            'points' => $this->points(),
            'eras'   => $this->eras(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function points(): array
    {
        $rows = $this->db->all(
            "SELECT ev.value_text AS stored,
                    ev.value_number AS year,
                    lf.label AS field_label,
                    e.id     AS entry_id,
                    e.title,
                    e.slug,
                    c.id     AS category_id,
                    c.name   AS archive,
                    c.slug   AS archive_slug,
                    c.icon,
                    c.color
               FROM entry_values ev
               JOIN layout_fields lf ON lf.id = ev.field_id
                                    AND lf.field_type = :type
                                    AND lf.archived_at IS NULL
               JOIN entries e ON e.id = ev.entry_id AND e.archived_at IS NULL
               JOIN categories c ON c.id = e.category_id
              WHERE ev.value_number IS NOT NULL
              ORDER BY ev.value_number ASC",
            ['type' => FieldTypes::DATE]
        );

        $out = [];
        foreach ($rows as $row) {
            $date = Calendar::decode($row['stored']);

            $out[] = [
                'entry_id' => (int) $row['entry_id'],
                'title'    => $row['title'],
                'field'    => $row['field_label'],
                'category' => (int) $row['category_id'],
                'archive'  => $row['archive'],
                'icon'     => $row['icon'],
                'color'    => $row['color'],
                'url'      => '/c/' . $row['archive_slug'] . '/e/' . $row['slug'],
                'year'     => (int) $row['year'],
                // Only present once the entry has a real calendar date; a legacy bare-year row has none.
                'kind'     => $date['kind'] ?? null,
                'ref'      => $date['ref'] ?? null,
                'day'      => $date['day'] ?? null,
            ];
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>> */
    private function eras(): array
    {
        $rows = $this->db->all(
            "SELECT ev.value_text AS span,
                    lf.label AS field_label,
                    e.id     AS entry_id,
                    e.title,
                    e.slug,
                    c.id     AS category_id,
                    c.name   AS archive,
                    c.slug   AS archive_slug,
                    c.icon,
                    c.color
               FROM entry_values ev
               JOIN layout_fields lf ON lf.id = ev.field_id
                                    AND lf.field_type = :type
                                    AND lf.archived_at IS NULL
               JOIN entries e ON e.id = ev.entry_id AND e.archived_at IS NULL
               JOIN categories c ON c.id = e.category_id
              WHERE ev.value_text IS NOT NULL",
            ['type' => FieldTypes::ERA]
        );

        $out = [];
        foreach ($rows as $row) {
            $span = Calendar::decodeEra($row['span']);

            // Both ends are needed to draw a bar; a half-filled era waits for the other.
            if ($span === null || $span['from'] === null || $span['to'] === null) {
                continue;
            }

            // Swap by year alone if the two ends were entered the wrong way round.
            $swap = $span['from']['year'] > $span['to']['year'];
            $from = $swap ? $span['to'] : $span['from'];
            $to = $swap ? $span['from'] : $span['to'];

            $out[] = [
                'entry_id'  => (int) $row['entry_id'],
                'title'     => $row['title'],
                'field'     => $row['field_label'],
                'category'  => (int) $row['category_id'],
                'archive'   => $row['archive'],
                'icon'      => $row['icon'],
                'color'     => $row['color'],
                'url'       => '/c/' . $row['archive_slug'] . '/e/' . $row['slug'],
                'from'      => $from['year'],
                'to'        => $to['year'],
                'fromKind'  => $from['kind'],
                'fromRef'   => $from['ref'],
                'fromDay'   => $from['day'],
                'toKind'    => $to['kind'],
                'toRef'     => $to['ref'],
                'toDay'     => $to['day'],
            ];
        }

        usort($out, static fn (array $a, array $b): int => $a['from'] <=> $b['from']);

        return $out;
    }
}
