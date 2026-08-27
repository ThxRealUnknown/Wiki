<?php

/**
 * Starter archives.
 */

use App\FieldTypes;

return [
    [
        'name'        => 'Characters',
        'icon'        => '👤',
        'color'       => '#c98a4b',
        'description' => 'The people of the world — and the things that pass for people.',
        'sort_order'  => 0,
        'layout'      => [
            'name'   => 'Character sheet',
            'fields' => [
                [
                    'label' => 'Portrait',
                    'type'  => FieldTypes::IMAGE,
                    'width' => 'full',
                ],
                [
                    'label'  => 'Also known as',
                    'type'   => FieldTypes::TEXT,
                    'width'  => 'half',
                    'help'   => 'Titles, epithets, aliases.',
                    'config' => ['placeholder' => 'The Grey Pilgrim'],
                ],
                [
                    'label'  => 'Status',
                    'type'   => FieldTypes::SELECT,
                    'width'  => 'half',
                    'config' => ['options' => "Alive\nDead\nMissing\nUnknown"],
                ],
                [
                    'label'  => 'Species',
                    'type'   => FieldTypes::RELATION,
                    'width'  => 'half',
                    'help'   => 'Links to an entry in the Species archive.',
                    'config' => ['multiple' => false, 'target' => 'Species'],
                ],
                [
                    'label'  => 'Born',
                    'type'   => FieldTypes::DATE,
                    'width'  => 'half',
                    'config' => ['placeholder' => 'Third Age, 2941'],
                ],
                [
                    'label' => 'Overview',
                    'type'  => FieldTypes::RICHTEXT,
                    'help'  => 'The short version: who they are and why they matter.',
                ],
                [
                    'label' => 'Appearance',
                    'type'  => FieldTypes::TEXTAREA,
                ],
                [
                    'label' => 'History',
                    'type'  => FieldTypes::RICHTEXT,
                ],
                [
                    'label'  => 'Traits',
                    'type'   => FieldTypes::TAGS,
                    'help'   => 'Type and press Enter.',
                    'config' => ['allow_custom' => true],
                ],
                [
                    'label' => 'Secrets',
                    'type'  => FieldTypes::RICHTEXT,
                    'help'  => 'Things the world does not know yet.',
                ],
            ],
        ],
    ],
    [
        'name'        => 'Species',
        'icon'        => '🧬',
        'color'       => '#5f9e6e',
        'description' => 'Peoples, beasts and everything in between.',
        'sort_order'  => 1,
        'layout'      => [
            'name'   => 'Species profile',
            'fields' => [
                [
                    'label' => 'Illustration',
                    'type'  => FieldTypes::IMAGE,
                ],
                [
                    'label' => 'Overview',
                    'type'  => FieldTypes::RICHTEXT,
                ],
                [
                    'label'  => 'Average lifespan',
                    'type'   => FieldTypes::NUMBER,
                    'width'  => 'half',
                    'config' => ['unit' => 'years'],
                ],
                [
                    'label'  => 'Habitat',
                    'type'   => FieldTypes::TEXT,
                    'width'  => 'half',
                ],
                [
                    'label' => 'Biology',
                    'type'  => FieldTypes::RICHTEXT,
                ],
                [
                    'label' => 'Culture',
                    'type'  => FieldTypes::RICHTEXT,
                ],
                [
                    'label'  => 'Traits',
                    'type'   => FieldTypes::TAGS,
                    'config' => ['allow_custom' => true],
                ],
            ],
        ],
    ],
    [
        'name'        => 'Magic Systems',
        'icon'        => '✦',
        'color'       => '#7b6cd9',
        'description' => 'How the impossible is made possible, and what it costs.',
        'sort_order'  => 2,
        'layout'      => [
            'name'   => 'Magic system',
            'fields' => [
                [
                    'label' => 'Premise',
                    'type'  => FieldTypes::RICHTEXT,
                    'help'  => 'One paragraph a reader could repeat back to you.',
                ],
                [
                    'label'  => 'Source of power',
                    'type'   => FieldTypes::TEXT,
                    'width'  => 'half',
                ],
                [
                    'label'  => 'Rarity',
                    'type'   => FieldTypes::SELECT,
                    'width'  => 'half',
                    'config' => ['options' => "Ubiquitous\nCommon\nUncommon\nRare\nUnique"],
                ],
                [
                    'label' => 'Rules',
                    'type'  => FieldTypes::RICHTEXT,
                    'help'  => 'What it can do — be specific, future you will thank you.',
                ],
                [
                    'label' => 'Limits and cost',
                    'type'  => FieldTypes::RICHTEXT,
                    'help'  => 'What it cannot do, and what using it takes out of you.',
                ],
                [
                    'label' => 'Practised by',
                    'type'  => FieldTypes::RELATION,
                    'help'  => 'Species or characters who wield it.',
                    'config' => ['multiple' => true],
                ],
            ],
        ],
    ],
    [
        'name'        => 'Locations',
        'icon'        => '🗺️',
        'color'       => '#4b8fc9',
        'description' => 'Places, from continents down to a single room that matters.',
        'sort_order'  => 3,
        'layouts'     => [
            [
                'name'   => 'Location',
                'fields' => [
                    [
                        'label' => 'Map or view',
                        'type'  => FieldTypes::IMAGE,
                    ],
                    [
                        'label' => 'Map area',
                        'type'  => FieldTypes::MAPAREA,
                        'help'  => 'Traced on the world map — open World map in the sidebar to draw it.',
                    ],
                    [
                        'label'  => 'Type',
                        'type'   => FieldTypes::SELECT,
                        'width'  => 'half',
                        'config' => ['options' => "Continent\nRegion\nCity\nTown\nVillage\nStronghold\nRuin\nWilderness"],
                    ],
                    [
                        'label'  => 'Population',
                        'type'   => FieldTypes::NUMBER,
                        'width'  => 'half',
                    ],
                    [
                        'label' => 'Overview',
                        'type'  => FieldTypes::RICHTEXT,
                    ],
                    [
                        'label' => 'Geography',
                        'type'  => FieldTypes::RICHTEXT,
                    ],
                    [
                        'label'  => 'Part of',
                        'type'   => FieldTypes::RELATION,
                        'width'  => 'half',
                        'help'   => 'The larger place this one sits inside.',
                        'config' => ['multiple' => false, 'target' => 'Locations'],
                    ],
                    [
                        'label'  => 'Notable inhabitants',
                        'type'   => FieldTypes::RELATION,
                        'config' => ['multiple' => true, 'target' => 'Characters'],
                    ],
                ],
            ],
            [
                'name'   => 'Place',
                'fields' => [
                    [
                        'label' => 'Picture',
                        'type'  => FieldTypes::IMAGE,
                    ],
                    [
                        'label' => 'Map point',
                        'type'  => FieldTypes::MAPPOINT,
                        'help'  => 'Placed on the world map — open World map in the sidebar to place it.',
                    ],
                    [
                        'label'  => 'Type',
                        'type'   => FieldTypes::SELECT,
                        'width'  => 'half',
                        'config' => ['options' => "Landmark\nBuilding\nRoom\nCamp\nRuin\nShrine\nOther"],
                    ],
                    [
                        'label' => 'Overview',
                        'type'  => FieldTypes::RICHTEXT,
                    ],
                    [
                        'label'  => 'Part of',
                        'type'   => FieldTypes::RELATION,
                        'width'  => 'half',
                        'help'   => 'The larger location this place sits inside.',
                        'config' => ['multiple' => false, 'target' => 'Locations'],
                    ],
                    [
                        'label'  => 'Notable inhabitants',
                        'type'   => FieldTypes::RELATION,
                        'config' => ['multiple' => true, 'target' => 'Characters'],
                    ],
                ],
            ],
			[
                'name'   => 'Path',
                'fields' => [
                    [
                        'label' => 'Picture',
                        'type'  => FieldTypes::IMAGE,
                    ],
                    [
                        'label' => 'Map point',
                        'type'  => FieldTypes::MAPPATH,
                        'help'  => 'Paths on the world map — open World map in the sidebar to place it.',
                    ],
                    [
                        'label'  => 'Type',
                        'type'   => FieldTypes::SELECT,
                        'width'  => 'half',
                        'config' => ['options' => "Landmark\nBuilding\nRoom\nCamp\nRuin\nShrine\nOther"],
                    ],
                    [
                        'label' => 'Overview',
                        'type'  => FieldTypes::RICHTEXT,
                    ],
                    [
                        'label'  => 'Part of',
                        'type'   => FieldTypes::RELATION,
                        'width'  => 'half',
                        'help'   => 'The larger location this place sits inside.',
                        'config' => ['multiple' => false, 'target' => 'Locations'],
                    ],
                    [
                        'label'  => 'Notable inhabitants',
                        'type'   => FieldTypes::RELATION,
                        'config' => ['multiple' => true, 'target' => 'Characters'],
                    ],
                ],
            ],
        ],
    ],
];
