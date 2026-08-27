<?php

namespace App;

/**
 * Stable identity for rows that travel outside the database. Auto-increment ids
 * only mean anything inside one database, so backups/imports match rows by guid
 * instead — regardless of renames. Never shown in the interface.
 */
final class Guid
{
    /** Tables that carry a guid column. */
    public const TABLES = ['categories', 'layouts', 'layout_fields', 'entries', 'chapters'];

    /** A random (version 4) UUID. */
    public static function make(): string
    {
        $bytes = random_bytes(16);

        // Version 4, variant 1, per RFC 4122.
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    /**
     * Gives a guid to every row that is still missing one.
     *
     * @param callable|null $log receives one line per table touched
     * @return int rows filled in
     */
    public static function backfill(Database $db, ?callable $log = null): int
    {
        $filled = 0;

        foreach (self::TABLES as $table) {
            if (!$db->tableExists($table)) {
                continue;
            }

            $rows = $db->all('SELECT id FROM ' . $table . ' WHERE guid IS NULL');
            if ($rows === []) {
                continue;
            }

            $db->transaction(static function (Database $db) use ($table, $rows): void {
                foreach ($rows as $row) {
                    $db->update($table, (int) $row['id'], ['guid' => self::make()]);
                }
            });

            $filled += count($rows);
            if ($log !== null) {
                $log(sprintf('%s: %d row(s) given an id', $table, count($rows)));
            }
        }

        return $filled;
    }
}
