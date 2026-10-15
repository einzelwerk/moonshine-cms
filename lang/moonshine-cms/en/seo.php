<?php

declare(strict_types=1);

return [
    'tab' => 'SEO',
    'language' => 'Language',
    'title' => 'Title in search results',
    'description' => 'Description in search results',

    'sections' => [
        'snippet' => 'How the page looks in search results',
        'social' => 'Preview in social networks and messengers',
        'twitter' => 'Preview on X (Twitter)',
        'indexing' => 'Indexing and the canonical address',
    ],

    'preview' => [
        'title' => 'Preview',
        'empty_title' => 'No title',
    ],

    'canonical' => [
        'invalid' => 'The canonical address is either a path starting with a slash, or a full address with http:// or https://.',
    ],
];
