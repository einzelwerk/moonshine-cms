<?php

declare(strict_types=1);

/**
 * Captions of the blocks the core itself ships. The content languages of a site
 * and the interface language of the package are unrelated.
 */
return [
    'text' => [
        'title' => 'Текст',
        'variants' => [
            'default' => 'Обычный',
            'narrow' => 'Узкая колонка',
        ],
        'fields' => [
            'title' => 'Заголовок',
            'body' => 'Текст',
        ],
    ],

    'toc' => [
        'title' => 'Оглавление',
        'fields' => [
            'title' => 'Заголовок',
        ],
        'help' => [
            'title' => 'Если оставить пустым, сайт покажет подпись по умолчанию.',
        ],
    ],

    'columns' => [
        'title' => 'Колонки',
        'variants' => [
            '2' => 'Две колонки',
            '3' => 'Три колонки',
            '4' => 'Четыре колонки',
            '1-2' => 'Узкая и широкая',
        ],
        'fields' => [
            'collapse_on_mobile' => 'Схлопывать в одну колонку на узком экране',
        ],
        'slot' => 'Колонка :position',
    ],

    'shared' => [
        'title' => 'Общий блок',
    ],
];
