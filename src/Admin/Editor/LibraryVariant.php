<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

/**
 * One variant card of a block type in the library. The icon key is neutral; the
 * markup beside it is what this interface mapped the key onto, see {@see
 * LibraryIcons}.
 */
final readonly class LibraryVariant
{
    public function __construct(
        public string $code,
        public string $title,
        public string $iconKey,
        public string $icon = '',
    ) {
    }
}
