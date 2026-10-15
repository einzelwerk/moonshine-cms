<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures\Blocks;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Fields\LinkField;
use Ewk\Cms\Blocks\Fields\MediaField;
use Ewk\Cms\Blocks\Fields\RepeaterField;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * The shape that broke the editor: a repeater whose row carries a picture and
 * a link. Both are composite values, both are renamed per row, and both used
 * to render as a fieldset with a caption per input.
 */
final class SlidesBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'slides';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Слайдер')
            ->category('content')
            ->fields([
                RepeaterField::make('slides')->label('Слайды')->fields([
                    TextField::make('title')->label('Заголовок')->translatable(),
                    MediaField::make('image')->label('Изображение'),
                    LinkField::make('link')->label('Ссылка')->allowedKinds(['url', 'page']),
                ]),
            ])
            ->placement(root: '*', parents: '*', shared: false);
    }
}
