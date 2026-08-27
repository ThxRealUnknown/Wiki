<?php

namespace App;

/**
 * The icons an archive can be given, grouped and searchable.
 *
 * Nothing here is enforced — an icon is just a short string in the database;
 * CategoryRepo::cleanIcon keeps at most the first two code points, so every
 * glyph here is at most a base character plus an optional variation selector
 * (multi-codepoint glyphs like flags would be cut in half).
 *
 * Keywords are what the search box matches on.
 */
final class Icons
{
    /** @var array<string, array<string, string>> group => glyph => keywords */
    private const GROUPS = [
        'People' => [
            '👤' => 'person figure anyone someone character',
            '👥' => 'people group crowd pair',
            '👑' => 'crown king queen monarch royalty ruler',
            '🤴' => 'prince king royal heir',
            '👸' => 'princess queen royal heir',
            '🧙' => 'wizard mage sorcerer witch magic',
            '🧝' => 'elf fae folk',
            '🧛' => 'vampire undead night',
            '🧟' => 'zombie undead risen',
            '🦸' => 'hero champion',
            '🧑' => 'person adult folk',
            '🧓' => 'elder old age wise',
            '👶' => 'child baby young',
            '💀' => 'skull death dead bone',
            '👻' => 'ghost spirit haunt',
            '🗣️' => 'speech voice language tongue speaking',
            '🤝' => 'pact alliance deal treaty agreement',
            '👣' => 'footprints trail journey travel',
            '🧜' => 'merfolk siren sea folk',
            '🥷' => 'assassin spy shadow killer',
        ],

        'Creatures' => [
            '🐉' => 'dragon wyrm drake beast',
            '🐺' => 'wolf beast pack hound',
            '🦁' => 'lion beast pride',
            '🐻' => 'bear beast',
            '🐎' => 'horse steed mount cavalry',
            '🦅' => 'eagle bird raptor',
            '🦉' => 'owl bird night wisdom',
            '🐦' => 'bird small fowl',
            '🕊️' => 'dove peace bird messenger',
            '🐍' => 'snake serpent scale',
            '🐊' => 'crocodile reptile beast',
            '🦂' => 'scorpion venom desert',
            '🕷️' => 'spider web venom',
            '🦇' => 'bat night cave',
            '🐗' => 'boar pig tusk beast',
            '🦌' => 'deer stag hart forest',
            '🐟' => 'fish sea river',
            '🐙' => 'octopus kraken sea tentacle',
            '🦖' => 'lizard saurian ancient beast',
            '🧬' => 'species bloodline lineage kind biology',
        ],

        'Places' => [
            '🏛️' => 'hall institution senate government system classical',
            '🏰' => 'castle keep fortress stronghold',
            '🏯' => 'castle eastern fortress',
            '🗼' => 'tower spire watchtower',
            '⛩️' => 'gate shrine torii',
            '🕌' => 'mosque temple faith',
            '⛪' => 'church temple faith chapel',
            '🕍' => 'temple synagogue faith',
            '🏘️' => 'village houses settlement town',
            '🛖' => 'hut dwelling shelter village',
            '🏚️' => 'ruin abandoned derelict',
            '🏕️' => 'camp tents encampment',
            '🏝️' => 'island isle shore',
            '🏔️' => 'mountain peak summit snow',
            '⛰️' => 'mountain hill highland',
            '🌋' => 'volcano fire mountain eruption',
            '🏜️' => 'desert sand waste dune',
            '🏞️' => 'valley park land countryside',
            '🌉' => 'bridge crossing span',
            '🚪' => 'door gate threshold entrance',
            '🗺️' => 'map region land territory place cartography',
            '📍' => 'place pin location marker spot',
        ],

        'Nature' => [
            '🌍' => 'world earth globe planet',
            '🌏' => 'world earth globe planet east',
            '🌑' => 'moon dark new night',
            '🌕' => 'moon full night',
            '☀️' => 'sun day light star',
            '⭐' => 'star night sky',
            '☄️' => 'comet omen sky falling',
            '🌊' => 'sea ocean wave water',
            '💧' => 'water drop rain river',
            '🔥' => 'fire flame burn heat',
            '❄️' => 'ice snow cold winter frost',
            '⚡' => 'lightning storm thunder power',
            '🌪️' => 'storm tornado wind whirlwind',
            '🌫️' => 'fog mist haze',
            '🌱' => 'seedling growth sprout new life',
            '🌲' => 'tree forest pine wood',
            '🌳' => 'tree forest oak wood',
            '🌾' => 'grain harvest wheat farming field peasant commoner',
            '🍃' => 'leaf wind nature',
            '🌸' => 'flower blossom spring',
            '🪨' => 'stone rock boulder',
            '🌐' => 'globe realm sphere world',
        ],

        'War' => [
            '⚔️' => 'war battle swords fight combat weapon',
            '🗡️' => 'sword blade dagger weapon',
            '🛡️' => 'shield defence guard protection',
            '🏹' => 'bow arrow archer ranged',
            '🪓' => 'axe weapon tool chop',
            '🔱' => 'trident spear sea weapon',
            '🎯' => 'target aim goal',
            '💣' => 'bomb blast destruction',
            '⚑' => 'banner flag faction standard house',
            '🚩' => 'flag banner claim marker',
            '🏴' => 'flag black banner rebellion',
            '🥁' => 'drum march war beat',
            '🩸' => 'blood wound sacrifice',
            '☠️' => 'death poison danger pirate',
        ],

        'Power' => [
            '⚖️' => 'law justice balance scales judgement philosophy',
            '📜' => 'scroll law decree history record charter',
            '🔑' => 'key access secret unlock',
            '🗝️' => 'key old secret unlock',
            '⛓️' => 'chains bondage slavery binding',
            '🔗' => 'link connection chain bond',
            '💰' => 'money wealth gold trade coin',
            '🪙' => 'coin money currency trade',
            '💎' => 'gem jewel treasure precious',
            '🎖️' => 'honour medal rank order',
            '🏆' => 'trophy victory prize',
            '👁️' => 'eye watch surveillance seeing order',
            '🕰️' => 'time clock era age history',
            '⌛' => 'time hourglass age passing',
        ],

        'Magic' => [
            '✦' => 'star magic spark arcane force',
            '✧' => 'star magic faint phenomenon force',
            '✨' => 'sparkle magic wonder enchantment',
            '🔮' => 'orb divination magic seeing future',
            '🪄' => 'wand magic spell',
            '🕯️' => 'candle ritual faith religion light vigil',
            '🧿' => 'ward charm amulet protection eye',
            '☯️' => 'balance duality philosophy harmony',
            '☮️' => 'peace accord',
            '✝️' => 'cross faith religion church',
            '☪️' => 'crescent faith religion',
            '✡️' => 'star faith religion',
            '☸️' => 'wheel faith dharma religion',
            '🜁' => 'air element alchemy',
            '🜂' => 'fire element alchemy',
            '🜃' => 'earth element alchemy',
            '🜄' => 'water element alchemy',
            '⚗️' => 'alchemy potion brewing transmutation',
            '🧪' => 'potion vial experiment brew',
            '⚱️' => 'urn ashes remains rite',
        ],

        'Craft' => [
            '📚' => 'books library knowledge encyclopedia lore',
            '📖' => 'book open reading text',
            '📔' => 'journal notebook notes',
            '✍️' => 'writing author quill hand',
            '🖋️' => 'pen quill writing ink',
            '🎓' => 'learning school academy scholar',
            '🧠' => 'mind thought intellect memory',
            '🔬' => 'science study research',
            '🔭' => 'telescope stars astronomy seeing',
            '🧭' => 'compass navigation direction bearing',
            '⚙️' => 'gear machine system mechanism',
            '⚒️' => 'tools smithing forge craft hammer',
            '⛏️' => 'pick mining stone digging',
            '⚓' => 'anchor sea harbour port ship',
            '⛵' => 'ship sail sea voyage',
            '🧵' => 'thread weaving cloth craft',
            '🎭' => 'culture theatre masks drama custom',
            '🎵' => 'music song melody',
            '🖼️' => 'art picture painting image',
            '🗿' => 'monument statue ancient stone',
            '🏺' => 'pottery vessel ancient artefact',
            '🔔' => 'bell call warning summons',
        ],

        'Marks' => [
            '•' => 'dot point plain default',
            '◆' => 'diamond mark solid',
            '◇' => 'diamond mark hollow',
            '◈' => 'diamond mark inset',
            '●' => 'circle dot solid',
            '○' => 'circle hollow ring',
            '◉' => 'circle target eye',
            '■' => 'square solid block',
            '□' => 'square hollow',
            '▲' => 'triangle up peak',
            '▼' => 'triangle down',
            '★' => 'star solid favourite',
            '☆' => 'star hollow',
            '☾' => 'moon crescent night',
            '☽' => 'moon crescent waxing',
            '§' => 'section law statute clause',
            '¶' => 'paragraph text passage',
            '†' => 'dagger death cross note',
            '‡' => 'double dagger note',
            '⁂' => 'asterism stars group cluster',
            '※' => 'reference note mark',
            '❖' => 'diamond ornament flourish',
            '✚' => 'cross plus healing aid',
            '⬡' => 'hexagon cell hollow',
            '⬢' => 'hexagon cell solid',
            '∞' => 'infinity endless eternal',
            '⧉' => 'layers copy duplicate',
            '➤' => 'arrow pointer direction',
        ],
    ];

    /** @return array<string, array<string, string>> */
    public static function groups(): array
    {
        return self::GROUPS;
    }

    /** How many there are altogether, for the picker to say so. */
    public static function count(): int
    {
        $total = 0;
        foreach (self::GROUPS as $icons) {
            $total += count($icons);
        }

        return $total;
    }
}
