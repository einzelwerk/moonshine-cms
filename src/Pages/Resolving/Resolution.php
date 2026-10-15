<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Resolving;

/**
 * The outcome of resolving an address. An outcome is a field of the answer and
 * not an address of its own.
 *
 * `LanguagePrefix` exists because a language code inside the path is almost
 * always an integration mistake — the language travels in `Accept-Language` —
 * and a silent 404 hides it. The transport turns this case into 400.
 */
enum Resolution: string
{
    /** A page of the tree, matched by its stored path. */
    case PAGE = 'page';

    /** An entity behind a template page type, matched by the path template. */
    case SUBJECT = 'subject';

    /** The path carries a language prefix; the language belongs in the header. */
    case LANGUAGE_PREFIX = 'language_prefix';

    case NOT_FOUND = 'not_found';
}
