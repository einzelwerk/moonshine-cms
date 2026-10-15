<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Nesting;

use Ewk\Cms\Blocks\Blocks\SharedInsertionBlock;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Contracts\ContainerBlockContract;
use Ewk\Cms\Blocks\Exceptions\BlockCycleException;
use Ewk\Cms\Blocks\Exceptions\InvalidBlockPlacementException;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Schema\Slot;

/**
 * Every nesting rule in one place, so the form and the move endpoint cannot
 * drift apart. A rule implemented only in the form is bypassed with a mouse,
 * which is exactly where the reviewed drag and drop packages leak.
 *
 * The rules of the slot and the rules of the block type combine through AND,
 * never OR: a block declaring itself a child of a container must not slip past
 * the container's white list.
 *
 * The minimum number of children is not checked here: an intermediate empty
 * slot must be saveable, so the minimum belongs to publication.
 */
final readonly class PlacementValidator
{
    public function __construct(
        private BlockRegistryInterface $registry,
        private int $maxDepth = 2,
    ) {
    }

    /**
     * @throws InvalidBlockPlacementException
     * @throws BlockCycleException
     */
    public function validate(CmsBlock $block): void
    {
        $this->guardSharedReference($block);

        $slot = $block->slot;

        if ($block->parent_id === null) {
            if ($slot !== null && $slot !== '') {
                throw InvalidBlockPlacementException::rootCarriesSlot($slot);
            }

            // Availability on the root level of a page is declared by the block
            // type per page type, and the page type lives outside this package:
            // the owner checks it.
            return;
        }

        if ($slot === null || $slot === '') {
            throw InvalidBlockPlacementException::childWithoutSlot();
        }

        $parent = CmsBlock::query()->find($block->parent_id);

        if ($parent === null) {
            throw InvalidBlockPlacementException::missingParent($block->parent_id);
        }

        if (! $this->sameOwner($block, $parent)) {
            throw InvalidBlockPlacementException::parentOfAnotherOwner();
        }

        $rows = $this->ownerRows($block);

        $this->guardCycle($block, $parent, $rows);

        $declared = $this->declaredSlot($parent, $slot);
        $type = $this->effectiveType($block) ?? throw InvalidBlockPlacementException::sharedBlockWithoutRoot(
            (int) $block->cms_shared_block_id,
        );

        if (! $declared->accepts($type)) {
            throw InvalidBlockPlacementException::typeNotAllowedInSlot($type, $slot);
        }

        $this->guardAllowedParents($type, $parent->type);
        $this->guardDepth($block, $parent, $rows);
        $this->guardSlotCapacity($block, $parent, $declared, $type, $rows);
    }

    /**
     * Changing the variant of a container is refused while a slot that the new
     * variant no longer declares still holds children: the editor takes the
     * content out first. We forbid what we cannot handle predictably instead of
     * losing content silently.
     *
     * @throws InvalidBlockPlacementException
     */
    public function validateVariantChange(CmsBlock $block): void
    {
        $definition = $this->registry->has($block->type) ? $this->registry->make($block->type) : null;

        if (! $definition instanceof ContainerBlockContract) {
            return;
        }

        $names = [];

        foreach ($definition->slots($block->variant) as $slot) {
            $names[$slot->name] = true;
        }

        $filled = CmsBlock::query()
            ->where('parent_id', $block->getKey())
            ->whereNotNull('slot')
            ->get(['slot']);

        foreach ($filled as $filledBlock) {
            $slot = (string) $filledBlock->slot;

            if (! isset($names[$slot])) {
                throw InvalidBlockPlacementException::variantDropsFilledSlot(
                    $block->type,
                    $block->variant,
                    $slot,
                );
            }
        }
    }

    /**
     * The slot a container declares under this name for its current variant.
     *
     * @throws InvalidBlockPlacementException
     */
    public function declaredSlot(CmsBlock $parent, string $slot): Slot
    {
        $definition = $this->registry->has($parent->type) ? $this->registry->make($parent->type) : null;

        if (! $definition instanceof ContainerBlockContract) {
            throw InvalidBlockPlacementException::parentIsNotAContainer($parent->type);
        }

        foreach ($definition->slots($parent->variant) as $declared) {
            if ($declared->name === $slot) {
                return $declared;
            }
        }

        throw InvalidBlockPlacementException::slotNotDeclared($parent->type, $parent->variant, $slot);
    }

    /**
     * Nesting restrictions are checked against the type of the original: an
     * insertion passes a slot accepting text when the original is a text block.
     */
    public function effectiveType(CmsBlock $block): ?string
    {
        if ($block->type !== SharedInsertionBlock::code()) {
            return $block->type;
        }

        if ($block->cms_shared_block_id === null) {
            return null;
        }

        return CmsSharedBlock::query()->find($block->cms_shared_block_id)?->rootBlock()?->type;
    }

    private function guardSharedReference(CmsBlock $block): void
    {
        if ($block->type !== SharedInsertionBlock::code()) {
            if ($block->cms_shared_block_id !== null) {
                throw InvalidBlockPlacementException::sharedReferenceOnPlainBlock($block->type);
            }

            return;
        }

        if ($block->cms_shared_block_id === null) {
            throw InvalidBlockPlacementException::insertionWithoutSharedBlock();
        }

        if ($block->blockable_type === (new CmsSharedBlock())->getMorphClass()) {
            throw InvalidBlockPlacementException::sharedBlockInsideLibrary();
        }

        if (CmsSharedBlock::query()->whereKey($block->cms_shared_block_id)->doesntExist()) {
            throw InvalidBlockPlacementException::missingSharedBlock($block->cms_shared_block_id);
        }
    }

    /**
     * @param  array<int, CmsBlock>  $rows
     */
    private function guardCycle(CmsBlock $block, CmsBlock $parent, array $rows): void
    {
        if (! $block->exists) {
            return;
        }

        $id = $block->getKey();
        $ancestor = $parent;
        $seen = [];

        while (true) {
            if ($ancestor->getKey() === $id) {
                throw BlockCycleException::forBlock($block->uuid);
            }

            $parentId = $ancestor->parent_id;

            if ($parentId === null || isset($seen[$parentId]) || ! isset($rows[$parentId])) {
                return;
            }

            $seen[$parentId] = true;
            $ancestor = $rows[$parentId];
        }
    }

    /**
     * The level of a block is the number of containers above it: a root block
     * is level 0. Moving a subtree counts its own height too, otherwise a move
     * would push grandchildren past the limit.
     *
     * @param  array<int, CmsBlock>  $rows
     */
    private function guardDepth(CmsBlock $block, CmsBlock $parent, array $rows): void
    {
        $level = $this->levelOf($parent, $rows) + 1 + $this->heightOf($block, $rows);

        if ($level > $this->maxDepth) {
            throw InvalidBlockPlacementException::maxDepthExceeded($level, $this->maxDepth);
        }
    }

    /**
     * @param  array<int, CmsBlock>  $rows
     */
    private function levelOf(CmsBlock $block, array $rows): int
    {
        $level = 0;
        $parentId = $block->parent_id;
        $seen = [];

        while ($parentId !== null && ! isset($seen[$parentId]) && isset($rows[$parentId])) {
            $seen[$parentId] = true;
            $level++;
            $parentId = $rows[$parentId]->parent_id;
        }

        return $level;
    }

    /**
     * @param  array<int, CmsBlock>  $rows
     */
    private function heightOf(CmsBlock $block, array $rows): int
    {
        if (! $block->exists) {
            return 0;
        }

        $children = [];

        foreach ($rows as $row) {
            if ($row->parent_id !== null) {
                $children[$row->parent_id][] = $row->id;
            }
        }

        return $this->heightBelow($block->id, $children, []);
    }

    /**
     * @param  array<int, list<int>>  $children
     * @param  array<int, true>  $seen
     */
    private function heightBelow(int $id, array $children, array $seen): int
    {
        if (isset($seen[$id])) {
            return 0;
        }

        $seen[$id] = true;
        $height = 0;

        foreach ($children[$id] ?? [] as $childId) {
            $height = max($height, 1 + $this->heightBelow($childId, $children, $seen));
        }

        return $height;
    }

    /**
     * @param  array<int, CmsBlock>  $rows
     */
    private function guardSlotCapacity(CmsBlock $block, CmsBlock $parent, Slot $slot, string $type, array $rows): void
    {
        $siblings = [];

        foreach ($rows as $row) {
            if ($row->parent_id === $parent->getKey() && $row->slot === $slot->name
                && $row->getKey() !== $block->getKey()) {
                $siblings[] = $row;
            }
        }

        if ($slot->max !== null && count($siblings) >= $slot->max) {
            throw InvalidBlockPlacementException::slotIsFull($slot->name, $slot->max);
        }

        if (! $slot->uniqueTypes) {
            return;
        }

        foreach ($siblings as $sibling) {
            if ($this->effectiveType($sibling) === $type) {
                throw InvalidBlockPlacementException::duplicateTypeInSlot($type, $slot->name);
            }
        }
    }

    private function guardAllowedParents(string $type, string $parentType): void
    {
        if (! $this->registry->has($type)) {
            // An unregistered type declares nothing; the renderer drops it from
            // the response instead.
            return;
        }

        $parents = $this->registry->schemaFor($type)->placementParents();

        if ($parents === '*') {
            return;
        }

        if (is_array($parents) && in_array($parentType, $parents, true)) {
            return;
        }

        throw InvalidBlockPlacementException::parentNotAllowedForType($type, $parentType);
    }

    private function sameOwner(CmsBlock $block, CmsBlock $parent): bool
    {
        return $parent->blockable_type === $block->blockable_type
            && (string) $parent->blockable_id === (string) $block->blockable_id;
    }

    /**
     * Every row of the owner in one query: the tree is assembled in memory.
     *
     * @return array<int, CmsBlock>
     */
    private function ownerRows(CmsBlock $block): array
    {
        $rows = CmsBlock::query()
            ->where('blockable_type', $block->blockable_type)
            ->where('blockable_id', $block->blockable_id)
            ->get(['id', 'parent_id', 'slot', 'type', 'variant', 'cms_shared_block_id']);

        $byId = [];

        foreach ($rows as $row) {
            $byId[$row->id] = $row;
        }

        return $byId;
    }
}
