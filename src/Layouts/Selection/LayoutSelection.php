<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Selection;

/**
 * How a layout was arrived at. Two steps exist, and the third case is the
 * honest admission that neither of them produced anything.
 */
enum LayoutSelection: string
{
    /** The page named a layout and it is active. */
    case REQUESTED = 'requested';

    /** Nothing was named, or what was named is unknown or switched off. */
    case DEFAULT = 'default';

    /** No layout carries the default flag: the installation is unfinished. */
    case NO_DEFAULT = 'no_default';
}
