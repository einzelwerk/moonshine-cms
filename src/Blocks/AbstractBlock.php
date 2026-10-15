<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks;

use Ewk\Cms\Blocks\Contracts\BlockContract;
use Ewk\Cms\Blocks\Http\Resources\BlockResource;
use Ewk\Cms\Blocks\Http\Resources\SchemaBlockResource;

/**
 * Base for block types. A simple block declares its schema and nothing else:
 * the payload follows the schema through {@see SchemaBlockResource}. A
 * dynamic block overrides {@see AbstractBlock::resource()} with its own
 * resource class.
 */
abstract class AbstractBlock implements BlockContract
{
    /**
     * @return class-string<BlockResource>
     */
    public function resource(): string
    {
        return SchemaBlockResource::class;
    }
}
