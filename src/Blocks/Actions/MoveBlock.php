<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Actions;

use Ewk\Cms\Blocks\Exceptions\BlockCycleException;
use Ewk\Cms\Blocks\Exceptions\InvalidBlockPlacementException;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Moving and reordering a block. This is a transactional operation of its own
 * and not a form save, because dragging is the one place where the parent of a
 * block changes without a form: the existing drag and drop mechanisms of the
 * stack update the parent without a single check.
 *
 * A move stays inside one owner. The parent, the slot and the position change;
 * the owner, the uuid and the anchor of the block survive it.
 *
 * Positions are dense inside the group `owner, parent, slot`, so both the
 * target group and the group the block came from are renumbered.
 */
final readonly class MoveBlock
{
    /**
     * @param  int|null  $parentId  container to move into, null for the root level
     * @param  string|null  $slot  slot of that container, null at the root level
     * @param  int|null  $position  index inside the slot, null appends
     *
     * @throws InvalidBlockPlacementException
     * @throws BlockCycleException
     */
    public function handle(CmsBlock $block, ?int $parentId, ?string $slot, ?int $position = null): CmsBlock
    {
        return DB::transaction(function () use ($block, $parentId, $slot, $position): CmsBlock {
            $sourceParentId = $block->parent_id;
            $sourceSlot = $block->slot;

            $block->parent_id = $parentId;
            $block->slot = $slot;

            $order = $this->siblingsOf($block, $parentId, $slot)
                ->whereKeyNot($block->getKey())
                ->pluck('id')
                ->all();

            $index = min(max($position ?? count($order), 0), count($order));
            array_splice($order, $index, 0, [$block->getKey()]);

            $block->sort_order = $index;

            // Validation of the placement runs inside the save; nothing has
            // been written before it, and the transaction covers the rest.
            $block->save();

            $this->renumber($order, $block);

            if ($sourceParentId !== $parentId || $sourceSlot !== $slot) {
                $this->compact($block, $sourceParentId, $sourceSlot);
            }

            return $block->refresh();
        });
    }

    /**
     * @param  array<array-key, mixed>  $order  row keys in their new order
     */
    private function renumber(array $order, CmsBlock $moved): void
    {
        foreach (array_values($order) as $position => $id) {
            if ($id === $moved->getKey()) {
                continue;
            }

            CmsBlock::query()->whereKey($id)->update(['sort_order' => $position]);
        }
    }

    private function compact(CmsBlock $block, ?int $parentId, ?string $slot): void
    {
        $ids = $this->siblingsOf($block, $parentId, $slot)->pluck('id')->all();

        foreach (array_values($ids) as $position => $id) {
            CmsBlock::query()->whereKey($id)->update(['sort_order' => $position]);
        }
    }

    /**
     * @return Builder<CmsBlock>
     */
    private function siblingsOf(CmsBlock $block, ?int $parentId, ?string $slot): Builder
    {
        $query = CmsBlock::query()
            ->where('blockable_type', $block->blockable_type)
            ->where('blockable_id', $block->blockable_id)
            ->orderBy('sort_order')
            ->orderBy('id');

        $parentId === null
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $parentId);

        $slot === null
            ? $query->whereNull('slot')
            : $query->where('slot', $slot);

        return $query;
    }
}
