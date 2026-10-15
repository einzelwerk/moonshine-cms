<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\AbstractBlock;
use Ewk\Cms\Blocks\Schema\BlockSchema;

/**
 * A second class claiming the `text` code, used to prove the registry rejects
 * a duplicate instead of quietly replacing the first one.
 */
final class DuplicateTextBlock extends AbstractBlock
{
    public static function code(): string
    {
        return 'text';
    }

    public function schema(): BlockSchema
    {
        return BlockSchema::make()->title('Другой текст');
    }
}
