<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Support;

use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Illuminate\Container\Container;

/**
 * A place for a menu in words: an editor picks «Footer, column 1» and never
 * `footer_2`.
 *
 * A place the installation declared together with its owner brings its own
 * words ({@see MenuSlotCatalog::labelOf()}), because this package cannot know
 * what the footer of a letter is called. The places of the header and the
 * footer are named by the translations of this package. A place an
 * installation added through `cms-layouts.menu_slots` has neither, so it shows
 * its own code: a wrong word would be worse than the code the installation
 * itself chose.
 */
final class MenuSlotLabel
{
    public static function of(string $code): string
    {
        $declared = self::catalog()?->labelOf($code);

        if ($declared !== null) {
            return $declared;
        }

        $label = __('moonshine-cms::menus.slot_labels.'.$code);

        return is_string($label) && $label !== 'moonshine-cms::menus.slot_labels.'.$code ? $label : $code;
    }

    private static function catalog(): ?MenuSlotCatalog
    {
        $container = Container::getInstance();

        if (! $container->bound(MenuSlotCatalog::class)) {
            return null;
        }

        /** @var MenuSlotCatalog $catalog */
        $catalog = $container->make(MenuSlotCatalog::class);

        return $catalog;
    }
}
