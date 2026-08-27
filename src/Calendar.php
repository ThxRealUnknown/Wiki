<?php

namespace App;

/**
 * A user-designed calendar: named months (each with its own day count), named
 * weekdays, optional intercalary day blocks and leap-year rules, and holidays
 * that recur yearly. Every Date/Era field value is a date under this calendar.
 *
 * A date is `['year' => int, 'kind' => 'month'|'intercalary', 'ref' => int,
 * 'day' => int]`. A holiday's `type` is 'date' (same kind/ref/day, no year),
 * 'weekday' (Nth weekday of a month; `occurrence` 1-5 or -1 for last), or
 * 'cycle' (recurs every N days from a reference date).
 */
final class Calendar
{
    private static ?array $cache = null;

    /** @var array<int, int> */
    private static array $yearLengthCache = [];

    public static function config(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $raw = Settings::get(Settings::CALENDAR_CONFIG);
        $decoded = $raw === null ? null : json_decode($raw, true);

        if (!self::isValid($decoded)) {
            return self::$cache = self::defaultConfig();
        }

        // Older saved configs may lack these keys; default to empty.
        $decoded['intercalary'] = is_array($decoded['intercalary'] ?? null) ? $decoded['intercalary'] : [];
        $decoded['leap_rules'] = is_array($decoded['leap_rules'] ?? null) ? $decoded['leap_rules'] : [];
        $decoded['holidays'] = is_array($decoded['holidays'] ?? null) ? $decoded['holidays'] : [];

        return self::$cache = $decoded;
    }

    public static function set(array $config): void
    {
        Settings::set(Settings::CALENDAR_CONFIG, json_encode($config, JSON_UNESCAPED_UNICODE));
        self::$cache = null;
        self::$yearLengthCache = [];
    }

    /** What a fresh install sees before anyone has designed a real calendar. */
    public static function defaultConfig(): array
    {
        $months = [];
        for ($i = 1; $i <= 12; $i++) {
            $months[] = ['name' => t('Month %d', $i), 'days' => 30];
        }

        $weekdays = [];
        for ($i = 1; $i <= 7; $i++) {
            $weekdays[] = t('Day %d', $i);
        }

        return [
            'months'      => $months,
            'weekdays'    => $weekdays,
            'intercalary' => [],
            'leap_rules'  => [],
            'holidays'    => [],
        ];
    }

    public static function isValid(mixed $config): bool
    {
        return is_array($config)
            && !empty($config['months']) && is_array($config['months'])
            && !empty($config['weekdays']) && is_array($config['weekdays']);
    }

    // ------------------------------------------------------------ arithmetic

    /** Floor mod (unlike PHP's %, which truncates toward zero), so periodic rules stay correct across year 0. */
    public static function floorMod(int $a, int $n): int
    {
        return (($a % $n) + $n) % $n;
    }

    /** A month's base length plus whatever leap rules add or subtract for this year. */
    public static function monthLength(int $year, int $month): int
    {
        $config = self::config();
        $months = $config['months'];
        $index = $month - 1;
        if ($index < 0 || $index >= count($months)) {
            return 0;
        }

        $days = (int) ($months[$index]['days'] ?? 0);

        foreach ($config['leap_rules'] as $rule) {
            if ((int) ($rule['month'] ?? 0) !== $month) {
                continue;
            }
            $every = max(1, (int) ($rule['every_years'] ?? 1));
            $offset = (int) ($rule['offset'] ?? 0);
            if (self::floorMod($year - $offset, $every) === 0) {
                $days += (int) ($rule['extra_days'] ?? 0);
            }
        }

        return max(0, $days);
    }

    /**
     * The year's full ordered sequence of slots: months in order, with
     * intercalary blocks spliced in at their `after_month` position (0 = before
     * month 1).
     *
     * @return array<int, array{kind:string, ref:int, label:string, days:int}>
     */
    public static function slots(int $year): array
    {
        $config = self::config();
        $months = $config['months'];
        $count = count($months);

        $byPosition = [];
        foreach ($config['intercalary'] as $i => $block) {
            $after = max(0, min($count, (int) ($block['after_month'] ?? 0)));
            $byPosition[$after][] = [
                'kind'  => 'intercalary',
                'ref'   => $i,
                'label' => (string) ($block['name'] ?? t('Special days')),
                'days'  => max(0, (int) ($block['days'] ?? 0)),
            ];
        }

        $slots = $byPosition[0] ?? [];
        foreach ($months as $index => $month) {
            $number = $index + 1;
            $slots[] = [
                'kind'  => 'month',
                'ref'   => $number,
                'label' => (string) ($month['name'] ?? t('Month %d', $number)),
                'days'  => self::monthLength($year, $number),
            ];
            foreach ($byPosition[$number] ?? [] as $slot) {
                $slots[] = $slot;
            }
        }

        return $slots;
    }

    public static function yearLength(int $year): int
    {
        if (isset(self::$yearLengthCache[$year])) {
            return self::$yearLengthCache[$year];
        }

        $total = 0;
        foreach (self::slots($year) as $slot) {
            $total += $slot['days'];
        }

        return self::$yearLengthCache[$year] = $total;
    }

    /**
     * Absolute linear day count from the epoch (year 1, day 1 = day 0), for
     * sorting and comparing dates. Uses a plain year-by-year loop rather than a
     * closed-form formula — simpler to trust with leap rules and negative years,
     * and fast enough for a single-world wiki. `yearLength()` is memoized to
     * keep it cheap.
     */
    public static function dayNumber(array $date): int
    {
        return self::accumulate($date, false);
    }

    /** Same as dayNumber(), but skips intercalary slots that don't count toward weekdays. Used only by weekdayOf(). */
    private static function weekdayCountedNumber(array $date): int
    {
        return self::accumulate($date, true);
    }

    private static function accumulate(array $date, bool $weekdayCounting): int
    {
        $year = (int) $date['year'];
        $total = 0;

        if ($year >= 1) {
            for ($y = 1; $y < $year; $y++) {
                $total += self::yearTotal($y, $weekdayCounting);
            }
        } else {
            for ($y = $year; $y < 1; $y++) {
                $total -= self::yearTotal($y, $weekdayCounting);
            }
        }

        $total += self::priorSlotDays($year, $date, $weekdayCounting);
        $total += max(0, (int) $date['day'] - 1);

        return $total;
    }

    private static function yearTotal(int $year, bool $weekdayCounting): int
    {
        if (!$weekdayCounting) {
            return self::yearLength($year);
        }

        $total = 0;
        foreach (self::slots($year) as $slot) {
            if (self::countsTowardWeekday($slot)) {
                $total += $slot['days'];
            }
        }

        return $total;
    }

    /** Days held by every slot in $year strictly before the one $date names. */
    private static function priorSlotDays(int $year, array $date, bool $weekdayCounting): int
    {
        $wantKind = (string) ($date['kind'] ?? 'month');
        $wantRef = (int) ($date['ref'] ?? 0);

        $total = 0;
        foreach (self::slots($year) as $slot) {
            if ($slot['kind'] === $wantKind && $slot['ref'] === $wantRef) {
                break;
            }
            if (!$weekdayCounting || self::countsTowardWeekday($slot)) {
                $total += $slot['days'];
            }
        }

        return $total;
    }

    private static function countsTowardWeekday(array $slot): bool
    {
        if ($slot['kind'] !== 'intercalary') {
            return true;
        }

        $block = self::config()['intercalary'][$slot['ref']] ?? null;

        return $block !== null && !empty($block['counts_weekday']);
    }

    /**
     * 0-based index into the configured weekday names, or null for a day that
     * does not take part in the week at all (a non-counting intercalary day).
     */
    public static function weekdayOf(array $date): ?int
    {
        $weekdayCount = count(self::config()['weekdays']);
        if ($weekdayCount === 0) {
            return null;
        }

        if (($date['kind'] ?? 'month') === 'intercalary') {
            $block = self::config()['intercalary'][(int) ($date['ref'] ?? -1)] ?? null;
            if ($block === null || empty($block['counts_weekday'])) {
                return null;
            }
        }

        return self::floorMod(self::weekdayCountedNumber($date), $weekdayCount);
    }

    /**
     * A date's position within its year, as a fraction in [0, 1) — refines
     * entry_values.value_number so same-year entries still sort correctly.
     */
    public static function sortValue(array $date): float
    {
        $year = (int) $date['year'];
        $yearLen = max(1, self::yearLength($year));
        $withinYear = self::priorSlotDays($year, $date, false) + max(0, (int) $date['day'] - 1);
        $fraction = min(0.999999, max(0, $withinYear) / $yearLen);

        return $year + $fraction;
    }

    // -------------------------------------------------------------- display

    /**
     * English's st/nd/rd/th suffix is English-specific formatting, not
     * vocabulary — locales that don't use it get their own rule here rather
     * than a translated string. German ordinals take a trailing period.
     */
    public static function ordinal(int $day): string
    {
        if (Language::locale() === 'de') {
            return $day . '.';
        }

        $abs = abs($day);
        if ($abs % 100 >= 11 && $abs % 100 <= 13) {
            $suffix = 'th';
        } else {
            $suffix = match ($abs % 10) {
                1       => 'st',
                2       => 'nd',
                3       => 'rd',
                default => 'th',
            };
        }

        return $day . $suffix;
    }

    /**
     * "3rd Firstmoon, 204 A.F." (or "3rd day of Yearsend..." for intercalary).
     * Degrades gracefully if the stored reference no longer matches the current
     * calendar shape — calendars can be redesigned after entries point into them.
     */
    public static function formatDate(array $date): string
    {
        $config = self::config();
        $year = (int) ($date['year'] ?? 0);
        $day = (int) ($date['day'] ?? 1);
        $ref = (int) ($date['ref'] ?? 1);
        $yearText = Timeline::formatYear($year);

        if (($date['kind'] ?? 'month') === 'intercalary') {
            $block = $config['intercalary'][$ref] ?? null;
            $label = $block !== null
                ? (string) $block['name']
                : t('special days (no longer defined)');

            return t('%s day of %s, %s', self::ordinal($day), $label, $yearText);
        }

        $month = $config['months'][$ref - 1] ?? null;
        $label = $month !== null
            ? (string) $month['name']
            : t('Month %d (no longer defined)', $ref);

        // Deliberately a generic "%s %s, %s" template — the only spot in the
        // catalog with no fixed word of its own, since the plain month-date
        // format is ordinal + name + year with nothing else to key on.
        return t('%s %s, %s', self::ordinal($day), $label, $yearText);
    }

    /** "3rd Firstmoon, 204 A.F. – 12th Secondmoon, 210 A.F." */
    public static function formatEra(array $from, array $to): string
    {
        return t('%s – %s', self::formatDate($from), self::formatDate($to));
    }

    // --------------------------------------------------------- holidays

    /**
     * Every holiday landing on this surface (a month or intercalary block, in a
     * given year), resolved server-side so the client just looks up a day number.
     *
     * @return array<int, array<int, string>> day => names of what falls on it
     */
    public static function holidaysForSurface(int $year, string $kind, int $ref, int $dayCount): array
    {
        $out = [];

        foreach (self::config()['holidays'] as $holiday) {
            $name = (string) ($holiday['name'] ?? '');
            $type = (string) ($holiday['type'] ?? 'date');

            $day = match ($type) {
                'date'    => self::dateHolidayDay($holiday, $kind, $ref),
                'weekday' => self::weekdayHolidayDay($holiday, $year, $kind, $ref, $dayCount),
                'cycle'   => null, // handled below — a cycle can land on more than one day
                default   => null,
            };

            if ($type === 'cycle') {
                foreach (self::cycleHolidayDays($holiday, $year, $kind, $ref, $dayCount) as $cycleDay) {
                    $out[$cycleDay][] = $name;
                }
                continue;
            }

            if ($day !== null && $day >= 1 && $day <= $dayCount) {
                $out[$day][] = $name;
            }
        }

        return $out;
    }

    private static function dateHolidayDay(array $holiday, string $kind, int $ref): ?int
    {
        if (($holiday['kind'] ?? 'month') !== $kind || (int) ($holiday['ref'] ?? -1) !== $ref) {
            return null;
        }

        return (int) ($holiday['day'] ?? 0);
    }

    /** Weekday rules only ever apply to a month — an intercalary block may not even have weekdays. */
    private static function weekdayHolidayDay(array $holiday, int $year, string $kind, int $ref, int $dayCount): ?int
    {
        if ($kind !== 'month') {
            return null;
        }

        $forMonth = (int) ($holiday['month'] ?? 0);
        if ($forMonth !== 0 && $forMonth !== $ref) {
            return null;
        }

        $wanted = (int) ($holiday['weekday'] ?? -1);
        $occurrence = (int) ($holiday['occurrence'] ?? 1);

        $matches = [];
        for ($d = 1; $d <= $dayCount; $d++) {
            if (self::weekdayOf(['year' => $year, 'kind' => 'month', 'ref' => $ref, 'day' => $d]) === $wanted) {
                $matches[] = $d;
            }
        }

        if ($matches === []) {
            return null;
        }

        return $occurrence === -1 ? end($matches) : ($matches[$occurrence - 1] ?? null);
    }

    /** @return array<int, int> */
    private static function cycleHolidayDays(array $holiday, int $year, string $kind, int $ref, int $dayCount): array
    {
        $every = max(1, (int) ($holiday['every_days'] ?? 0));
        $start = self::normalise($holiday['start'] ?? null);
        if ($start === null) {
            return [];
        }

        $startNumber = self::dayNumber($start);
        $days = [];

        for ($d = 1; $d <= $dayCount; $d++) {
            $thisNumber = self::dayNumber(['year' => $year, 'kind' => $kind, 'ref' => $ref, 'day' => $d]);
            if (self::floorMod($thisNumber - $startNumber, $every) === 0) {
                $days[] = $d;
            }
        }

        return $days;
    }

    // ------------------------------------------------------ storage helpers

    /** The `<select>` value a slot round-trips through: "m:3" or "i:0". */
    public static function slotValue(array $date): string
    {
        $kind = ($date['kind'] ?? 'month') === 'intercalary' ? 'i' : 'm';

        return $kind . ':' . (int) ($date['ref'] ?? 1);
    }

    /** "m:3" / "i:0" into ['kind' => 'month'|'intercalary', 'ref' => int], or null if malformed. */
    public static function parseSlotToken(mixed $slot): ?array
    {
        $slotText = trim((string) $slot);
        if (!preg_match('/^(m|i):(\d+)$/', $slotText, $m)) {
            return null;
        }

        return ['kind' => $m[1] === 'm' ? 'month' : 'intercalary', 'ref' => (int) $m[2]];
    }

    /** Whether this slot exists structurally (month/intercalary block present) — unlike full date validation, doesn't need a year. */
    public static function slotRefExists(string $kind, int $ref, int $monthCount, int $intercalaryCount): bool
    {
        return $kind === 'month'
            ? ($ref >= 1 && $ref <= $monthCount)
            : ($ref >= 0 && $ref < $intercalaryCount);
    }

    /**
     * A posted year + slot value + day into a valid, clamped date — or null
     * if any part is missing or the slot no longer exists for that year.
     */
    public static function parseDate(mixed $year, mixed $slot, mixed $day): ?array
    {
        $year = self::parseSignedInt($year);
        $day = self::parseSignedInt($day);
        $token = self::parseSlotToken($slot);

        if ($year === null || $day === null || $token === null) {
            return null;
        }

        $kind = $token['kind'];
        $ref = $token['ref'];

        $match = null;
        foreach (self::slots($year) as $candidate) {
            if ($candidate['kind'] === $kind && $candidate['ref'] === $ref) {
                $match = $candidate;
                break;
            }
        }
        if ($match === null || $match['days'] <= 0) {
            return null;
        }

        return [
            'year' => $year,
            'kind' => $kind,
            'ref'  => $ref,
            'day'  => max(1, min($match['days'], $day)),
        ];
    }

    private static function parseSignedInt(mixed $raw): ?int
    {
        $text = trim((string) $raw);

        return ($text !== '' && ctype_digit(ltrim($text, '-'))) ? (int) $text : null;
    }

    public static function encode(array $date): string
    {
        return json_encode([
            'year' => (int) $date['year'],
            'kind' => ($date['kind'] ?? 'month') === 'intercalary' ? 'intercalary' : 'month',
            'ref'  => (int) $date['ref'],
            'day'  => (int) $date['day'],
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function decode(?string $stored): ?array
    {
        if ($stored === null || $stored === '') {
            return null;
        }

        return self::normalise(json_decode($stored, true));
    }

    /** @return array{from: ?array, to: ?array}|null */
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
            'from' => self::normalise($decoded['from'] ?? null),
            'to'   => self::normalise($decoded['to'] ?? null),
        ];
    }

    /** @return string|null JSON, or null if neither end was given */
    public static function encodeEra(?array $from, ?array $to): ?string
    {
        if ($from === null && $to === null) {
            return null;
        }

        return json_encode([
            'from' => $from === null ? null : [
                'year' => (int) $from['year'], 'kind' => $from['kind'],
                'ref' => (int) $from['ref'], 'day' => (int) $from['day'],
            ],
            'to' => $to === null ? null : [
                'year' => (int) $to['year'], 'kind' => $to['kind'],
                'ref' => (int) $to['ref'], 'day' => (int) $to['day'],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    private static function normalise(mixed $decoded): ?array
    {
        if (!is_array($decoded) || !isset($decoded['year'], $decoded['kind'], $decoded['ref'], $decoded['day'])) {
            return null;
        }

        return [
            'year' => (int) $decoded['year'],
            'kind' => $decoded['kind'] === 'intercalary' ? 'intercalary' : 'month',
            'ref'  => (int) $decoded['ref'],
            'day'  => (int) $decoded['day'],
        ];
    }
}
