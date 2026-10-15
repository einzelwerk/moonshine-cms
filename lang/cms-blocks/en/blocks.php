<?php

declare(strict_types=1);

/**
 * Captions of the blocks the core itself ships. The content languages of a site
 * and the interface language of the package are unrelated.
 */
return [
    'text' => [
        'title' => 'Text',
        'variants' => [
            'default' => 'Plain',
            'narrow' => 'Narrow column',
        ],
        'fields' => [
            'title' => 'Heading',
            'body' => 'Text',
        ],
    ],

    'toc' => [
        'title' => 'Table of contents',
        'fields' => [
            'title' => 'Heading',
        ],
        'help' => [
            'title' => 'Left empty, the site shows its default caption.',
        ],
    ],

    'columns' => [
        'title' => 'Columns',
        'variants' => [
            '2' => 'Two columns',
            '3' => 'Three columns',
            '4' => 'Four columns',
            '1-2' => 'Narrow and wide',
        ],
        'fields' => [
            'collapse_on_mobile' => 'Collapse into one column on a narrow screen',
        ],
        'slot' => 'Column :position',
    ],

    'shared' => [
        'title' => 'Shared block',
    ],
];
