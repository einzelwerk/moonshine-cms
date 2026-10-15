<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Http\Resolve;

/**
 * What resolving an address came to. It is the outcome of the service and not
 * an HTTP status: the transport turns it into 200, 400 or 404, and nothing but
 * the transport knows those numbers.
 */
enum ResolveStatus: string
{
    case FOUND = 'found';

    /**
     * The path carries a language prefix. Almost always an integration
     * mistake — the language travels in `Accept-Language` — so it is reported
     * instead of hidden behind a miss.
     */
    case LANGUAGE_PREFIX = 'language_prefix';

    case NOT_FOUND = 'not_found';
}
