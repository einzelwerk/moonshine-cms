<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Contracts;

/**
 * Marks a block resource whose payload depends on the anchors of the other
 * blocks of the same owner (the table of contents being the first case).
 *
 * Such a resource is rendered twice: once to learn that it participates, then
 * again with the anchors collected from its siblings. The mechanism is
 * deliberately generic, so container-aware anchor collection can reuse it.
 */
interface CollectsOwnerAnchors
{
    /**
     * @param  list<array{anchor: string, title: string}>  $anchors
     */
    public function withCollectedAnchors(array $anchors): static;
}
