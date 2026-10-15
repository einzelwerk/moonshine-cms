<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Exceptions;

use Ewk\Cms\Layouts\Models\CmsMenuItem;
use RuntimeException;

/**
 * The structural refusals of a menu item. The depth limit is the important one:
 * a third level that arrives empty in the response, as it does in other CMS
 * starters, is learned about from an editor instead of from a log, so it is
 * refused at write time.
 */
final class InvalidMenuItemPlacementException extends RuntimeException
{
    public static function depthExceeded(): self
    {
        return new self(sprintf(
            'A menu item cannot hang under a child item: menus hold %d levels.',
            CmsMenuItem::MAX_DEPTH,
        ));
    }

    public static function parentWithChildren(int $childCount): self
    {
        return new self(sprintf(
            'This item has %d child item(s) and cannot become a child itself: that would make a third level.',
            $childCount,
        ));
    }

    public static function intoItself(): self
    {
        return new self('A menu item cannot be its own parent.');
    }

    public static function acrossMenus(): self
    {
        return new self('A menu item cannot hang under an item of another menu.');
    }

    public static function unknownParent(int $parentId): self
    {
        return new self(sprintf('Parent menu item [%d] does not exist.', $parentId));
    }
}
