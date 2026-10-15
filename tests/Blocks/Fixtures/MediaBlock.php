<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Fields\MediaField;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A block with a media field and no payload schema of its own: the shape the
 * core answers with, a reference to a record of the library rather than a
 * hydrated picture.
 */
final class MediaBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'media_card';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Карточка с картинкой')
            ->fields([
                MediaField::make('image')->label('Изображение'),
            ])
            ->placement(root: '*', parents: '*', shared: false);
    }
}
