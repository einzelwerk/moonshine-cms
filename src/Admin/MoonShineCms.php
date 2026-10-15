<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms;

use Ewk\Cms\Media\MoonShine\Pages\MediaLibraryPage;
use Ewk\MoonShineCms\MoonShine\Resources\FooterResource;
use Ewk\MoonShineCms\MoonShine\Resources\HeaderResource;
use Ewk\MoonShineCms\MoonShine\Resources\LayoutResource;
use Ewk\MoonShineCms\MoonShine\Resources\MenuItemResource;
use Ewk\MoonShineCms\MoonShine\Resources\MenuResource;
use Ewk\MoonShineCms\MoonShine\Resources\MenuSlotResource;
use Ewk\MoonShineCms\MoonShine\Resources\PageResource;
use Ewk\MoonShineCms\MoonShine\Resources\SharedBlockResource;
use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;
use MoonShine\Contracts\Core\ResourceContract;
use MoonShine\Contracts\MenuManager\MenuElementContract;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;

/**
 * The sections the package adds to the panel. The provider registers them;
 * the lists are public so that an installation which registers its panel by
 * hand can do the same.
 */
final class MoonShineCms
{
    /**
     * @return list<class-string<ResourceContract>>
     */
    public static function resources(Repository $config): array
    {
        return [
            self::pageResource($config),
            SharedBlockResource::class,
            LayoutResource::class,
            HeaderResource::class,
            FooterResource::class,
            MenuResource::class,
            MenuItemResource::class,
            MenuSlotResource::class,
        ];
    }

    /**
     * The sections as an editor reads them: what is filled every day first, and
     * the settings of the site around the content folded into a group of their
     * own.
     *
     * The autoloaded menu of MoonShine reads one `Group` attribute per
     * resource and knows no second level, so an installation that wants this
     * order builds its «Content» group out of this list; the attributes on the
     * resources stay the flat default of a panel that does not.
     *
     * @return list<MenuElementContract>
     */
    public static function menuItems(Repository $config): array
    {
        return [
            MenuItem::make(self::pageResource($config)),
            MenuItem::make(SharedBlockResource::class),
            // Right after the blocks: a picture is what an editor goes
            // looking for on the way to filling one.
            MenuItem::make(
                MediaLibraryPage::class,
                static fn (): string => (string) __('moonshine-media::library.title'),
                'photo',
            ),
            MenuGroup::make(
                static fn (): string => (string) __('moonshine-cms::chrome.appearance'),
                [
                    MenuItem::make(LayoutResource::class),
                    MenuItem::make(HeaderResource::class),
                    MenuItem::make(FooterResource::class),
                    MenuItem::make(MenuResource::class),
                ],
                'swatch',
            ),
        ];
    }

    /**
     * The resource of pages of the installation: the package one, or the
     * subclass an application names in `moonshine-cms.page_resource` to add
     * what belongs to it.
     *
     * @return class-string<PageResource>
     */
    public static function pageResource(Repository $config): string
    {
        $configured = $config->get('moonshine-cms.page_resource', PageResource::class);

        if (! is_string($configured) || ! is_a($configured, PageResource::class, true)) {
            throw new InvalidArgumentException(sprintf(
                'moonshine-cms.page_resource must name a subclass of %s, [%s] given.',
                PageResource::class,
                is_string($configured) ? $configured : get_debug_type($configured),
            ));
        }

        return $configured;
    }
}
