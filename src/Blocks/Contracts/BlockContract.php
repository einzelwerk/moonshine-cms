<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Contracts;

use Ewk\Cms\Blocks\Http\Resources\BlockResource;
use Ewk\Cms\Blocks\Schema\BlockSchema;

interface BlockContract
{
    /**
     * Registry code of the block type; also the payload discriminator.
     */
    public static function code(): string;

    /**
     * The single declarative description of the block: form, validation,
     * storage shape, payload and API description are all derived from it.
     */
    public function schema(): BlockSchema;

    /**
     * Resource class building this block's `data` payload.
     *
     * @return class-string<BlockResource>
     */
    public function resource(): string;
}
