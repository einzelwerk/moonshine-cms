<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A list declared without a continuation, where the editor of one block may
 * turn one on: a selection on the front page shows a fixed row until someone
 * asks it to grow.
 */
final class SwitchableListBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'switchable';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Подборка с выбором режима')
            ->pagination(
                mode: PaginationMode::NONE,
                perPage: 6,
                modeEditable: true,
                allowedModes: [PaginationMode::NONE, PaginationMode::MORE],
            )
            ->placement(root: '*', parents: '*', shared: false);
    }

    public function resource(): string
    {
        return DynamicListBlockResource::class;
    }
}
