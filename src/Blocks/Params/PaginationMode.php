<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Params;

/**
 * How a list continues past its first screen. The value of the case is the
 * discriminator the frontend narrows the payload by.
 */
enum PaginationMode: string
{
    /** Numbered pages of a catalogue; the position is an offset. */
    case PAGES = 'pages';

    /** A growing slice behind a "show more" button, or a cursor. */
    case MORE = 'more';

    /** A fixed list with no continuation at all. */
    case NONE = 'none';
}
