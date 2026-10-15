<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

/**
 * One card of the editor. No mini preview is rendered: that would need a second
 * renderer next to the frontend one and the two would drift apart. The card
 * shows the title, the type, the variant and the state.
 *
 * The head reads «type: what the block says», and the second half is taken from
 * the content and never typed by hand: an editor names nothing twice. A block
 * whose first text field is still empty shows its type alone.
 *
 * A card of an inserted shared block shows no fields of its own and carries the
 * warning that editing the original shows everywhere.
 *
 * The icon is the markup of the type's own glyph, the same one the insertion
 * library draws, see {@see LibraryIcons}: a card is recognised by the picture
 * it was picked by. A type nobody declared an icon for carries an empty
 * string, and the head then shows the badge alone.
 */
final readonly class BlockCard
{
    /**
     * @param  array<array-key, mixed>  $content  stored values, already loaded with the row
     * @param  list<SlotView>  $slots
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public string $type,
        public string $typeTitle,
        public string $name,
        public string $caption,
        public ?string $variant,
        public ?string $variantTitle,
        public ?string $anchor,
        public bool $isActive,
        public string $version,
        public bool $shared = false,
        public ?string $sharedWarning = null,
        public ?int $sharedBlockId = null,
        public bool $removable = true,
        public bool $switchable = true,
        public bool $known = true,
        public string $icon = '',
        public array $content = [],
        public array $slots = [],
    ) {
    }

    public function isContainer(): bool
    {
        return $this->slots !== [];
    }
}
