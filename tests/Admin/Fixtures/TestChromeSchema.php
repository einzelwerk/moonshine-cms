<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures;

use Ewk\Cms\Blocks\Fields\MediaField;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Layouts\Chrome\ChromeKind;
use Ewk\Cms\Layouts\Chrome\ChromeSchema;
use Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry;

/**
 * What an installation declares for its header: a couple of fields of the
 * block dictionary. The footer stays undeclared on purpose, so that the form
 * of a kind without content is covered too.
 */
final class TestChromeSchema
{
    public static function register(ChromeSchemaRegistry $registry): void
    {
        $registry->register(
            ChromeKind::HEADER,
            ChromeSchema::make()
                ->title('Шапка')
                ->fields([
                    TextField::make('tagline')->label('Слоган')->translatable()->maxLength(60),
                    TextField::make('phone')->label('Телефон')->required(),
                    MediaField::make('logo')->label('Логотип'),
                    // A second picture on the very same form: the dialog of
                    // the library belongs to the document and not to a field,
                    // so exactly one of the two draws it.
                    MediaField::make('badge')->label('Значок'),
                ]),
        );
    }
}
