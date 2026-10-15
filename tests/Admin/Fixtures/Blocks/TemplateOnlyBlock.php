<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures\Blocks;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A block that lives on the template of one page type only, the way a
 * schedule block belongs to a tour page and to nothing else.
 */
final class TemplateOnlyBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'schedule';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Расписание')
            ->category('template')
            ->fields([
                TextField::make('caption')->label('Подпись'),
            ])
            ->placement(root: ['concert'], parents: [], shared: false);
    }
}
