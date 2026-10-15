<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Contracts\ContainerBlockContract;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A container whose current variant declares no slots at all — the shape a
 * real container takes when a variant is switched to one without columns, or
 * while a type is being written. Its `children` must still be a map.
 */
final class SlotlessContainerBlock extends AbstractBlock implements ContainerBlockContract
{
    public static function code(): string
    {
        return 'test_slotless';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Контейнер без слотов')
            ->fields([])
            ->placement(root: '*', parents: '*', shared: false);
    }

    public function slots(?string $variant): array
    {
        return [];
    }
}
