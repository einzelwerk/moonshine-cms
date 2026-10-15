<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Contracts\ContainerBlockContract;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\Cms\Blocks\Schema\Slot;

/**
 * A container whose single slot declares every restriction the shipped
 * `columns` leaves open: a white list, a maximum and the ban on a repeated
 * type.
 */
final class SingleSlotContainerBlock extends AbstractBlock implements ContainerBlockContract
{
    public static function code(): string
    {
        return 'test_single_slot';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Один слот')
            ->fields([])
            ->placement(root: '*', parents: '*', shared: false);
    }

    public function slots(?string $variant): array
    {
        return [
            new Slot(
                name: 'only',
                title: 'Единственный слот',
                allow: ['text', 'toc'],
                min: 0,
                max: 2,
                span: 12,
                uniqueTypes: true,
            ),
        ];
    }
}
