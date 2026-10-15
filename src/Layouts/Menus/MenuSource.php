<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Menus;

use Ewk\Cms\Layouts\Models\CmsMenu;

/**
 * Where the items of a menu come from.
 *
 * `cms_menus.source` is a description and never executable code: the chrome
 * document always delivers the rows of `cms_menu_items`. A source fills those
 * rows on an explicit request of the editor — the button in the admin panel —
 * and not at read time, so that there is exactly one way for an item to
 * appear. The manual source is the absence of one and is not registered.
 */
interface MenuSource
{
    /**
     * The value stored in `cms_menus.source`.
     */
    public function code(): string;

    /**
     * How the source is called in the form of a menu.
     */
    public function title(): string;

    /**
     * Rewrite the items of the menu from the source.
     *
     * @return int how many items the menu holds afterwards; zero when the
     *             source has nothing to point at
     */
    public function fill(CmsMenu $menu): int;
}
