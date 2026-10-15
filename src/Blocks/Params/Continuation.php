<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

/**
 * The transport of a {@see PaginationMode::MORE} list. The two cannot be mixed
 * inside one block: a growing slice and a cursor page have a different notion
 * of position.
 */
enum Continuation: string
{
    /** The position lives in the address of the page, as `b[anchor][shown]`. */
    case URL = 'url';

    /** The position lives in an opaque cursor of the block's own endpoint. */
    case ENDPOINT = 'endpoint';
}
