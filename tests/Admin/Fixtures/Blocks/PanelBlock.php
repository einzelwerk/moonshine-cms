<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures\Blocks;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Contracts\ContainerBlockContract;
use Ewk\Cms\Blocks\Fields\TextField;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\Cms\Blocks\Schema\Slot;

/**
 * A container with one named slot that accepts text blocks only, so the
 * library narrowed by a slot has something to narrow.
 */
final class PanelBlock extends AbstractBlock implements ContainerBlockContract
{
    public static function code(): string
    {
        return 'panel';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Панель')
            ->category('layout')
            ->fields([
                TextField::make('caption')->label('Подпись'),
            ])
            ->placement(root: '*', parents: [], shared: false);
    }

    public function slots(?string $variant): array
    {
        return [
            new Slot(
                name: 'body',
                title: 'Содержимое',
                allow: ['text'],
                min: 1,
                max: 2,
                span: 12,
            ),
        ];
    }
}
