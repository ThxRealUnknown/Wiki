<?php

namespace App;

/**
 * The registry of field types a layout can be built from. Adding a type here
 * plus a case in views/entries/_field_input.php and _field_display.php is all
 * it takes to extend the system.
 */
final class FieldTypes
{
    public const TEXT     = 'text';
    public const TEXTAREA = 'textarea';
    public const RICHTEXT = 'richtext';
    public const NUMBER   = 'number';
    public const DATE     = 'date';
    public const ERA      = 'era';
    public const SELECT   = 'select';
    public const TAGS     = 'tags';
    public const IMAGE    = 'image';
    public const BANNER   = 'banner';
    public const RELATION = 'relation';
    public const MAPAREA  = 'maparea';
    public const MAPPOINT = 'mappoint';
    public const MAPPATH  = 'mappath';

    /**
     * @return array<string, array{label:string, hint:string, group:string}>
     */
    public static function all(): array
    {
        return [
            self::TEXT => [
                'label' => t('Short text'),
                'hint'  => t('One line — a name, an epithet, a title.'),
                'group' => t('Text'),
            ],
            self::TEXTAREA => [
                'label' => t('Long text'),
                'hint'  => t('Several plain lines, no formatting.'),
                'group' => t('Text'),
            ],
            self::RICHTEXT => [
                'label' => t('Rich text'),
                'hint'  => t('Formatted prose: headings, bold, lists, links.'),
                'group' => t('Text'),
            ],
            self::NUMBER => [
                'label' => t('Number'),
                'hint'  => t('Height, population, year, power level.'),
                'group' => t('Data'),
            ],
            self::DATE => [
                'label' => t('Date'),
                'hint'  => t('A day under your own calendar — shown, and clickable, on the timeline.'),
                'group' => t('Timeline'),
            ],
            self::ERA => [
                'label' => t('Era'),
                'hint'  => t("A span of years — a reign, a war, an age — drawn as a bar on the timeline, coloured by this entry's archive."),
                'group' => t('Timeline'),
            ],
            self::SELECT => [
                'label' => t('Choice'),
                'hint'  => t('Pick one from a list you define.'),
                'group' => t('Data'),
            ],
            self::TAGS => [
                'label' => t('Tags'),
                'hint'  => t('Several labels at once, free-form or from a list.'),
                'group' => t('Data'),
            ],
            self::IMAGE => [
                'label' => t('Image'),
                'hint'  => t('A portrait, map or reference picture, shown where the field sits.'),
                'group' => t('Media'),
            ],
            self::BANNER => [
                'label' => t('Banner'),
                'hint'  => t('A wide image across the top of the page, above the title.'),
                'group' => t('Media'),
            ],
            self::RELATION => [
                'label' => t('Link to entries'),
                'hint'  => t('Point at other entries. Backlinks appear automatically.'),
                'group' => t('Connections'),
            ],
            self::MAPAREA => [
                'label' => t('Map area'),
                'hint'  => t('Where this sits on the world map. Shows a cutout of its own patch of the world.'),
                'group' => t('Media'),
            ],
            self::MAPPOINT => [
                'label' => t('Map point'),
                'hint'  => t('A single spot on the world map — a city, a keep, a ruin.'),
                'group' => t('Media'),
            ],
            self::MAPPATH => [
                'label' => t('Map path'),
                'hint'  => t('A route, river or border on the world map — drawn as a line, not a filled area.'),
                'group' => t('Media'),
            ],
        ];
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    public static function label(string $type): string
    {
        return self::all()[$type]['label'] ?? $type;
    }

    /** Stored in entry_links rather than entry_values. */
    public static function isRelation(string $type): bool
    {
        return $type === self::RELATION;
    }

    /** Also written to entry_values.value_number so it can be sorted on. */
    public static function isNumeric(string $type): bool
    {
        return $type === self::NUMBER;
    }

    /** Value is a JSON-encoded list rather than a scalar. */
    public static function isMultiValue(string $type): bool
    {
        return $type === self::TAGS;
    }

    /** A from/to pair, posted as an array and stored as JSON, like TAGS. */
    public static function isEra(string $type): bool
    {
        return $type === self::ERA;
    }

    /** Stores an uploaded file path and goes through the upload pipeline. */
    public static function isUpload(string $type): bool
    {
        return $type === self::IMAGE || $type === self::BANNER;
    }

    /** Rendered above the entry title rather than in the field grid. */
    public static function isBanner(string $type): bool
    {
        return $type === self::BANNER;
    }

    /** Whether a relation field's links each carry a label from its own list. */
    public static function isTypedRelation(array $config): bool
    {
        return !empty($config['typed']) && !empty($config['types']);
    }

    /**
     * The archives a relation field may point into, as ids (empty = no
     * restriction). Reads both `target_category_ids` and the older single
     * `target_category_id`, for layouts saved before multiple targets existed.
     *
     * @return array<int, int>
     */
    public static function relationTargets(array $config): array
    {
        if (array_key_exists('target_category_ids', $config)) {
            return self::cleanIdList($config['target_category_ids']);
        }

        return self::cleanIdList($config['target_category_id'] ?? null);
    }

    /** @return array<int, int> */
    private static function cleanIdList(mixed $raw): array
    {
        if ($raw === null || $raw === '' || $raw === []) {
            return [];
        }

        if (!is_array($raw)) {
            $raw = is_string($raw) ? explode(',', $raw) : [$raw];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $raw),
            static fn (int $id) => $id > 0
        )));
    }

    /**
     * Normalises whatever the layout editor posted into the config shape the
     * rest of the app expects, dropping anything the type does not use.
     */
    public static function normaliseConfig(string $type, array $raw): array
    {
        $config = [];

        switch ($type) {
            case self::SELECT:
            case self::TAGS:
                $options = $raw['options'] ?? '';
                if (is_string($options)) {
                    $options = preg_split('/\r\n|\r|\n/', $options) ?: [];
                }
                $options = array_values(array_filter(
                    array_map('trim', (array) $options),
                    static fn ($o) => $o !== ''
                ));
                $config['options'] = $options;
                if ($type === self::TAGS) {
                    $config['allow_custom'] = !empty($raw['allow_custom']);
                }
                if ($type === self::SELECT) {
                    // Offers this field as a sort order in the entry list.
                    $config['sortable'] = !empty($raw['sortable']);
                }
                break;

            case self::RELATION:
                // A relation may target several archives; empty means anywhere.
                $targets = $raw['target_category_ids'] ?? null;

                if ($targets === null && array_key_exists('target_category_id', $raw)) {
                    $targets = $raw['target_category_id'];   // the older single value
                }

                $config['target_category_ids'] = self::cleanIdList($targets);
                $config['multiple'] = !empty($raw['multiple']);

                // Typed relations: each link carries a label from a list the
                // field itself defines (e.g. Mother, Ally).
                $config['typed'] = !empty($raw['typed']);
                $types = $raw['types'] ?? '';
                if (is_string($types)) {
                    $types = preg_split('/\r\n|\r|\n/', $types) ?: [];
                }
                $config['types'] = array_values(array_filter(
                    array_map('trim', (array) $types),
                    static fn ($t) => $t !== ''
                ));
                break;

            case self::NUMBER:
                $unit = trim((string) ($raw['unit'] ?? ''));
                if ($unit !== '') {
                    $config['unit'] = $unit;
                }
                break;

            case self::TEXT:
            case self::TEXTAREA:
                $placeholder = trim((string) ($raw['placeholder'] ?? ''));
                if ($placeholder !== '') {
                    $config['placeholder'] = $placeholder;
                }
                break;
        }

        return $config;
    }
}
