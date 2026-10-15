<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Tests\Fixtures;

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Layouts\Models\CmsLayout;
use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\Cms\Layouts\Models\CmsMenuItem;
use Ewk\Cms\Layouts\Models\CmsMenuSlotAssignment;
use Ewk\Cms\Media\Models\CmsMedia;

/**
 * Rows for the tests. Plain model writes on purpose: the checks of this
 * package live on the models, and a factory that bypassed them would test
 * something else.
 */
final class Make
{
    /**
     * @param  array<array-key, mixed>|null  $content
     */
    public static function header(string $code = 'main', ?array $content = null, bool $active = true): CmsHeader
    {
        return CmsHeader::query()->create([
            'code' => $code,
            'name' => ['ru' => 'Шапка '.$code, 'en' => 'Header '.$code],
            'is_active' => $active,
            'content' => $content,
        ]);
    }

    /**
     * @param  array<array-key, mixed>|null  $content
     */
    public static function footer(string $code = 'main', ?array $content = null, bool $active = true): CmsFooter
    {
        return CmsFooter::query()->create([
            'code' => $code,
            'name' => ['ru' => 'Подвал '.$code, 'en' => 'Footer '.$code],
            'is_active' => $active,
            'content' => $content,
        ]);
    }

    public static function layout(
        string $code = 'default',
        bool $default = false,
        ?CmsHeader $header = null,
        ?CmsFooter $footer = null,
        bool $active = true,
        int $sortOrder = 0,
    ): CmsLayout {
        return CmsLayout::query()->create([
            'code' => $code,
            'name' => ['ru' => 'Лейаут '.$code, 'en' => 'Layout '.$code],
            'is_active' => $active,
            'is_default' => $default,
            'cms_header_id' => $header?->id,
            'cms_footer_id' => $footer?->id,
            'container' => 'wide',
            'body_class' => 'layout-'.$code,
            'sort_order' => $sortOrder,
        ]);
    }

    public static function menu(string $code = 'main', bool $active = true): CmsMenu
    {
        return CmsMenu::query()->create([
            'code' => $code,
            'name' => ['ru' => 'Меню '.$code, 'en' => 'Menu '.$code],
            'is_active' => $active,
        ]);
    }

    public static function item(
        CmsMenu $menu,
        string $label = 'Item',
        ?Link $link = null,
        ?CmsMenuItem $parent = null,
        int $sortOrder = 0,
        bool $active = true,
    ): CmsMenuItem {
        return CmsMenuItem::query()->create([
            'cms_menu_id' => $menu->id,
            'parent_id' => $parent?->id,
            'sort_order' => $sortOrder,
            'label' => ['ru' => $label, 'en' => $label],
            'link' => $link?->toArray(),
            'is_active' => $active,
        ]);
    }

    public static function slot(
        CmsHeader|CmsFooter $owner,
        CmsMenu $menu,
        string $slot = 'header_primary',
        bool $enabled = true,
        int $sortOrder = 0,
    ): CmsMenuSlotAssignment {
        return CmsMenuSlotAssignment::query()->create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->id,
            'slot' => $slot,
            'cms_menu_id' => $menu->id,
            'is_enabled' => $enabled,
            'sort_order' => $sortOrder,
        ]);
    }

    public static function logo(): CmsMedia
    {
        return CmsMedia::query()->create([
            'disk' => 'public',
            'path' => 'ab/'.uniqid('', true).'/v1/original.svg',
            'original_name' => 'logo.svg',
            'mime' => 'image/svg+xml',
            'size' => 512,
        ]);
    }
}
