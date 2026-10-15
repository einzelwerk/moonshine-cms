<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

/**
 * One entry of the insertion library: a registered block type, or a record of
 * the shared block library.
 *
 * A shared entry is marked as such and carries the warning that editing the
 * original shows everywhere it is inserted.
 *
 * `iconKey` stays the neutral key the server names; `icon` is the markup the
 * interface resolved it to, see {@see LibraryIcons}.
 */
final readonly class LibraryEntry
{
    /**
     * @param  list<LibraryVariant>  $variants
     */
    public function __construct(
        public string $code,
        public string $title,
        public ?string $category,
        public string $iconKey,
        public string $icon = '',
        public ?string $about = null,
        public array $variants = [],
        public bool $shared = false,
        public ?int $sharedBlockId = null,
        public ?string $warning = null,
    ) {
    }

    public function matches(string $search): bool
    {
        if ($search === '') {
            return true;
        }

        return mb_stripos($this->title, $search) !== false
            || mb_stripos($this->code, $search) !== false;
    }
}
