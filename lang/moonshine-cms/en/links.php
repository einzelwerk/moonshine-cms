<?php

declare(strict_types=1);

return [
    'kind' => 'Where it leads',

    'kinds' => [
        'entity' => 'Catalogue record',
        'page' => 'Site page',
        'list' => 'Catalogue with a filter',
        'anchor' => 'Section of this page',
        'file' => 'File',
        'action' => 'Action',
        'route' => 'Service page',
        'url' => 'External link',
        'email' => 'E-mail',
        'phone' => 'Phone',
    ],

    'steps' => [
        'page' => 'Page',
        'entity' => 'Record',
        'anchor' => 'Section',
        'catalog_page' => 'Catalogue page',
        'anchor_page' => 'Page with the section',
        'file' => 'File',
        'action' => 'Action',
        'route' => 'Address',
        'route_param' => 'Value of «:name»',
        'url' => 'Address',
        'email' => 'E-mail address',
        'phone' => 'Phone number',
        'list' => 'Which list to filter',
        'filters' => 'Filters',
    ],

    'choose' => 'Choose a value',
    'choose_file' => 'Choose a file',
    'clear_file' => 'Remove the file',
    'any' => 'Any',
    'new_tab' => 'Open in a new tab',
    'page_caption' => ':name (:path)',

    'placeholders' => [
        'url' => 'https://example.com',
        'email' => 'name@example.com',
        'phone' => '+420123456789',
    ],

    'hints' => [
        'url' => 'Full address with the http or https scheme',
        'phone' => 'Number in the international format',
    ],

    'boolean' => [
        'yes' => 'Yes',
        'no' => 'No',
    ],

    'empty' => [
        'anchors' => 'This page carries no sections with anchors',
        'anchor_page' => 'Choose a page first',
        'filters' => 'This catalogue declares no filters',
        'catalog_page' => 'Choose the catalogue page first',
        'actions' => 'There are no actions yet',
        'routes' => 'There are no service addresses yet',
        'kinds' => 'This field offers no link kind',
        'file' => 'No file chosen yet',
    ],

    'actions' => [
        'buy' => 'Ticket purchase',
    ],
];
