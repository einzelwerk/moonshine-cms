<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Fields\GroupField;
use Ewk\Cms\Blocks\Fields\MediaField;
use Ewk\Cms\Blocks\Fields\RepeaterField;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * Media in every place the schema vocabulary allows one: at the top level,
 * inside a group, inside a repeater, behind a dictionary of languages and
 * inside a repeater that is itself translatable. The accounting of usages has
 * to find all five.
 */
final class MediaGalleryBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'media_gallery';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Галерея')
            ->fields([
                MediaField::make('cover')->label('Обложка'),
                MediaField::make('poster')->label('Постер')->translatable(),
                GroupField::make('brand')->label('Бренд')->fields([
                    MediaField::make('logo')->label('Логотип'),
                ]),
                RepeaterField::make('slides')->label('Слайды')->fields([
                    TextField::make('caption')->label('Подпись'),
                    MediaField::make('photo')->label('Снимок'),
                ]),
                RepeaterField::make('promos')->label('Промо')->translatable()->fields([
                    TextField::make('caption')->label('Подпись'),
                    MediaField::make('banner')->label('Баннер'),
                ]),
            ])
            ->placement(root: '*', parents: '*', shared: false);
    }
}
