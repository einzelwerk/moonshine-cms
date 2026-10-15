<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Tests\Fixtures;

use Ewk\Cms\Blocks\Fields\BooleanField;
use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Blocks\Fields\MediaField;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Layouts\Chrome\ChromeKind;
use Ewk\Cms\Layouts\Chrome\ChromeSchema;
use Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry;

/**
 * What a header and a footer hold in this test installation, declared with the
 * very field types of the blocks — the point is that no second field dictionary
 * exists.
 */
final class TestChromeSchemas
{
    public static function registerAll(ChromeSchemaRegistry $registry): void
    {
        $registry->register(ChromeKind::HEADER, self::header());
        $registry->register(ChromeKind::FOOTER, self::footer());
    }

    public static function header(): ChromeSchema
    {
        return ChromeSchema::make()
            ->title('Header')
            ->fields([
                MediaField::make('logo')->label('Logo'),
                BooleanField::make('sticky')->label('Sticky')->default(false),
            ]);
    }

    public static function footer(): ChromeSchema
    {
        return ChromeSchema::make()
            ->title('Footer')
            ->fields([
                TextField::make('copyright')->label('Copyright')->translatable()->required(),
                LinkField::make('brand_link')->label('Brand link'),
            ]);
    }
}
