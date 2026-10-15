<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

/**
 * A group of the library modal. The `shared` group is the separate tab of the
 * shared block library and is marked so the UI can draw it apart.
 */
final readonly class LibraryCategory
{
    public const SHARED = 'shared';

    /**
     * @param  list<LibraryEntry>  $entries
     */
    public function __construct(
        public string $code,
        public string $title,
        public array $entries,
        public bool $shared = false,
    ) {
    }
}
