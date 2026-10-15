<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Exceptions;

use RuntimeException;

/**
 * A placement that breaks one of the nesting rules, see {@see
 * \Ewk\Cms\Blocks\Nesting\PlacementValidator}. Nothing is written and nothing
 * is truncated: an invalid child is rejected with a reason instead of being
 * silently dropped, which is the mistake of every block editor that drops what
 * does not fit.
 */
final class InvalidBlockPlacementException extends RuntimeException
{
    public static function rootCarriesSlot(string $slot): self
    {
        return new self("A block without a parent cannot sit in slot [{$slot}].");
    }

    public static function childWithoutSlot(): self
    {
        return new self('A nested block must name the slot of its parent.');
    }

    public static function parentOfAnotherOwner(): self
    {
        return new self('A block can only be nested under a parent of the same owner.');
    }

    public static function missingParent(int $parentId): self
    {
        return new self("Parent block [{$parentId}] does not exist.");
    }

    public static function parentIsNotAContainer(string $type): self
    {
        return new self("Block type [{$type}] declares no slots and cannot hold children.");
    }

    public static function slotNotDeclared(string $type, ?string $variant, string $slot): self
    {
        $variant ??= 'default';

        return new self("Block type [{$type}] variant [{$variant}] declares no slot [{$slot}].");
    }

    public static function typeNotAllowedInSlot(string $type, string $slot): self
    {
        return new self("Block type [{$type}] is not allowed in slot [{$slot}].");
    }

    public static function parentNotAllowedForType(string $type, string $parentType): self
    {
        return new self("Block type [{$type}] is not allowed inside [{$parentType}].");
    }

    public static function maxDepthExceeded(int $depth, int $maxDepth): self
    {
        return new self("Nesting level {$depth} exceeds the maximum level {$maxDepth} of the package.");
    }

    public static function slotIsFull(string $slot, int $max): self
    {
        return new self("Slot [{$slot}] accepts at most {$max} child block(s).");
    }

    public static function duplicateTypeInSlot(string $type, string $slot): self
    {
        return new self("Slot [{$slot}] already holds a block of type [{$type}].");
    }

    public static function variantDropsFilledSlot(string $type, ?string $variant, string $slot): self
    {
        $variant ??= 'default';

        return new self(
            "Variant [{$variant}] of block type [{$type}] declares no slot [{$slot}], and that slot is not empty."
        );
    }

    public static function sharedReferenceOnPlainBlock(string $type): self
    {
        return new self("Only a block of type [shared] may reference a shared block, got [{$type}].");
    }

    public static function insertionWithoutSharedBlock(): self
    {
        return new self('A block of type [shared] must reference a shared block.');
    }

    public static function missingSharedBlock(int $sharedBlockId): self
    {
        return new self("Shared block [{$sharedBlockId}] does not exist.");
    }

    public static function sharedBlockInsideLibrary(): self
    {
        return new self('A shared block cannot hold an insertion of another shared block.');
    }

    public static function sharedBlockWithoutRoot(int $sharedBlockId): self
    {
        return new self(
            "Shared block [{$sharedBlockId}] has no root block yet, so its placement cannot be checked."
        );
    }
}
