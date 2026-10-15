<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Menus;

use Ewk\Cms\Layouts\Chrome\ChromeKind;

/**
 * The slots of the header and the footer. Each one is a place in the markup of
 * the frontend, which is why the dictionary is closed and travels into the API
 * description as an enumeration: an open list would oblige the frontend to draw
 * what it does not know.
 *
 * An installation whose markup has more places extends the list through
 * `cms-layouts.menu_slots`, and declares places of records of its own, with
 * their owner, through {@see MenuSlotDefinition}; {@see MenuSlotCatalog} is
 * what everything else asks, never this enum directly.
 */
enum MenuSlot: string
{
    case HEADER_PRIMARY = 'header_primary';
    case HEADER_SECONDARY = 'header_secondary';
    case FOOTER_FIRST = 'footer_1';
    case FOOTER_SECOND = 'footer_2';
    case FOOTER_THIRD = 'footer_3';
    case LEGAL = 'legal';
    case SOCIAL = 'social';

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_map(static fn (self $slot): string => $slot->value, self::cases());
    }

    /**
     * Which part of the chrome owns the place. A slot belongs to the header or
     * to the footer and never to the layout, so the pair «place, owner» is
     * known from the declaration and is not asked of an editor a second time
     * when a menu says where it is shown.
     */
    public function kind(): ChromeKind
    {
        return match ($this) {
            self::HEADER_PRIMARY, self::HEADER_SECONDARY => ChromeKind::HEADER,
            default => ChromeKind::FOOTER,
        };
    }
}
