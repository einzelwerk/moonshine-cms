<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tree;

/**
 * One node of the page tree as the admin panel draws it.
 */
final readonly class PageNode
{
    /**
     * @param  list<PageNode>  $children
     */
    public function __construct(
        public int $id,
        public string $caption,
        public string $path,
        public string $type,
        public string $typeTitle,
        public bool $isActive,
        /** A singleton: its address is declared, so it is neither moved nor deleted. */
        public bool $fixed,
        public int $depth,
        public string $formUrl,
        public string $blocksUrl,
        public string $addChildUrl,
        public array $children,
    ) {
    }
}
