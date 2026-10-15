<?php

declare(strict_types=1);

return [
    /*
     * How deep blocks may nest: a root block is level 0, a block inside a
     * container is level 1. Levels 0 to 2 are allowed in the first version;
     * raising the limit needs no migration.
     */
    'max_depth' => 2,

    /*
     * Absolute ceilings of a paginated block. Neither the schema of a block,
     * nor a setting of one, nor a query parameter may exceed them: the
     * effective limit is the smaller of the schema value and the one here.
     *
     * The growing slice of the `more` mode reselects what has already been
     * shown, so its ceiling is a condition of the mode working at all, not a
     * formality.
     */
    'pagination' => [
        'default_per_page' => 10,
        'max_per_page' => 100,
        'max_shown' => 500,
    ],

    /*
     * Registered block types, explicitly listed: the registry never scans the
     * filesystem. A consuming application publishes this config to add its
     * own block classes or to drop the ones shipped here.
     */
    'blocks' => [
        \Ewk\Cms\Blocks\Blocks\TextBlock::class,
        \Ewk\Cms\Blocks\Blocks\TocBlock::class,
        \Ewk\Cms\Blocks\Blocks\ColumnsBlock::class,
        \Ewk\Cms\Blocks\Blocks\SharedInsertionBlock::class,
    ],
];
