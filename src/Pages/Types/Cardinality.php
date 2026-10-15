<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Types;

/**
 * How many pages a type holds and where its address comes from.
 *
 * | Cardinality | How many | Subject | Address |
 * |---|---|---|---|
 * | many | as many as the editor creates | no | from the tree |
 * | single | exactly one, undeletable | no | declared by the type |
 * | template | one per type, never opened by its own address | yes | from the path template |
 */
enum Cardinality: string
{
    case MANY = 'many';

    case SINGLE = 'single';

    case TEMPLATE = 'template';
}
