<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Events;

use Ewk\Cms\Blocks\Models\CmsBlock;

/**
 * Dispatched when storage carries a block type absent from the registry. The
 * renderer excludes such a block from the public response; listen to this event
 * to log or clean the rows up.
 */
final readonly class UnknownBlockTypeEncountered
{
    public function __construct(
        public string $uuid,
        public string $type,
        public string $blockableType,
        public int|string|null $blockableId,
    ) {
    }

    public static function forBlock(CmsBlock $block): self
    {
        return new self(
            uuid: $block->uuid,
            type: $block->type,
            blockableType: $block->blockable_type,
            blockableId: $block->blockable_id,
        );
    }
}
