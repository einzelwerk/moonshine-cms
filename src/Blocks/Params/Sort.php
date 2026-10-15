<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

/**
 * The order of a list, declared once and applied to every portion of it. An
 * order arriving as a free string from the query is a source of duplicates and
 * gaps between portions, not flexibility: a visitor chooses out of a declared
 * set through an {@see EnumParam} instead.
 */
final readonly class Sort
{
    public function __construct(
        public string $field,
        public string $direction = 'asc',
    ) {
    }
}
