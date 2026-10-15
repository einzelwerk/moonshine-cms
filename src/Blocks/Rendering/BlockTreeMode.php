<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Rendering;

/**
 * Which state a block tree is assembled from: the live rows are the draft the
 * editor works on, the snapshot is what a visitor gets.
 *
 * The mode also decides how an insertion of a shared block resolves, because
 * the snapshot stores a reference and not the expanded content: a published
 * page shows the published state of the shared block.
 */
enum BlockTreeMode
{
    /** Live rows; insertions resolve against the live rows of the library. */
    case DRAFT;

    /** Snapshot rows; insertions resolve against the library's own snapshot. */
    case PUBLISHED;

    /** Live rows of one owner alone, with insertions left unresolved. */
    case OWN;
}
