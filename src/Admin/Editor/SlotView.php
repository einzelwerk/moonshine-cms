<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

/**
 * A named place of a container as the editor draws it: the slot is labelled,
 * its children are nested cards, and dragging works inside it and between
 * slots.
 */
final readonly class SlotView
{
    /**
     * @param  list<BlockCard>  $children
     */
    public function __construct(
        public string $name,
        public string $title,
        public int $span,
        public int $min,
        public ?int $max,
        public array $children,
    ) {
    }

    public function isFull(): bool
    {
        return $this->max !== null && count($this->children) >= $this->max;
    }
}
