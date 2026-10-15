<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Chrome;

/**
 * The two editable parts of the chrome. They are separate records with their
 * own codes and their own declared fields, which is why the kind is a
 * dictionary of exactly two entries and not an open string.
 */
enum ChromeKind: string
{
    case HEADER = 'header';
    case FOOTER = 'footer';
}
