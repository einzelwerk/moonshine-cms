<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Params\PaginationMode;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A growing list without a ceiling of its own: the one of the package config
 * applies, and it is not a multiple of the portion.
 */
final class OpenFeedBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'open_feed';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()
            ->title('Подборка без своего потолка')
            ->pagination(mode: PaginationMode::MORE, perPage: 12)
            ->placement(root: '*', parents: '*', shared: false);
    }

    public function resource(): string
    {
        return DynamicListBlockResource::class;
    }
}
