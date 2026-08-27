<?php

namespace App;

/**
 * Formats a year against the wiki's named epoch (e.g. "204 A.F."), consistently
 * everywhere a year is shown. Years before the epoch use a minus sign rather
 * than a separate "before" name.
 */
final class Timeline
{
    public static function epochName(): string
    {
        return Settings::get(Settings::TIMELINE_EPOCH_NAME, '') ?? '';
    }

    public static function epochAbbr(): string
    {
        return Settings::get(Settings::TIMELINE_EPOCH_ABBR, '') ?? '';
    }

    /** "204 A.F." / "−58 A.F." — or just the bare number if no abbreviation is set. */
    public static function formatYear(int $year): string
    {
        $abbr = self::epochAbbr();
        $number = $year < 0 ? '−' . abs($year) : (string) $year;

        return $abbr === '' ? $number : $number . ' ' . $abbr;
    }

    /** Parses a posted value as a year; anything not a clean integer is treated as absent. */
    public static function parseYear(mixed $raw): ?int
    {
        $text = trim((string) $raw);

        return ($text !== '' && ctype_digit(ltrim($text, '-'))) ? (int) $text : null;
    }

    /**
     * @return string|null JSON, or null if neither end was given
     */
    public static function encodeEra(?int $from, ?int $to): ?string
    {
        if ($from === null && $to === null) {
            return null;
        }

        return json_encode(['from' => $from, 'to' => $to], JSON_UNESCAPED_UNICODE);
    }

    /** @return array{from: ?int, to: ?int}|null */
    public static function decodeEra(?string $stored): ?array
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        $decoded = json_decode($stored, true);
        if (!is_array($decoded)) {
            return null;
        }

        return [
            'from' => isset($decoded['from']) ? (int) $decoded['from'] : null,
            'to'   => isset($decoded['to']) ? (int) $decoded['to'] : null,
        ];
    }
}
