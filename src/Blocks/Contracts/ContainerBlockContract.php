<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Contracts;

use Ewk\Cms\Blocks\Schema\Slot;

/**
 * A block type that holds other blocks in named slots. The core ships the
 * mechanism; concrete visual containers are ordinary registered block types.
 */
interface ContainerBlockContract extends BlockContract
{
    /**
     * Slots of the container for the given variant, in the order the payload
     * presents them. The number of slots may depend on the variant: the
     * `columns` variant `3` declares three of them.
     *
     * @return list<Slot>
     */
    public function slots(?string $variant): array;
}
