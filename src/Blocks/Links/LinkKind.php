<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Links;

/**
 * The kinds of link targets. The database stores the kind of the target and its
 * identifier, never a ready address.
 */
enum LinkKind: string
{
    case ENTITY = 'entity';
    case PAGE = 'page';
    case LISTING = 'list';
    case ANCHOR = 'anchor';
    case FILE = 'file';
    case ROUTE = 'route';
    case URL = 'url';
    case EMAIL = 'email';
    case PHONE = 'phone';
    case ACTION = 'action';
}
