<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

use Ewk\Cms\Blocks\Actions\MoveBlock;
use Ewk\Cms\Blocks\Blocks\SharedInsertionBlock;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\MoonShineCms\Support\ModelKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Inserting a block from the library. Insertion happens at an exact position —
 * between two blocks, at the top, at the end or inside a named slot of a
 * container — and never only at the end.
 *
 * The position is applied through {@see MoveBlock}: placement and dense
 * ordering have one enforcement point, and a new row must pass it like a
 * dragged one.
 */
final readonly class InsertBlock
{
    public function __construct(
        private BlockRegistryInterface $registry,
        private MoveBlock $move,
    ) {
    }

    /**
     * Content stays empty: declared defaults are applied by the core when the
     * payload is built, so a fresh row does not freeze them.
     */
    public function handle(
        Model $owner,
        string $code,
        ?string $variant = null,
        ?int $parentId = null,
        ?string $slot = null,
        ?int $position = null,
    ): CmsBlock {
        $schema = $this->registry->schemaFor($code);

        return $this->create($owner, [
            'type' => $code,
            'variant' => $variant,
            'name' => $schema->title(),
        ], $parentId, $slot, $position);
    }

    /**
     * An entry of the shared library becomes an ordinary row of type `shared`
     * with a foreign key to the original.
     */
    public function handleShared(
        Model $owner,
        CmsSharedBlock $sharedBlock,
        ?int $parentId = null,
        ?string $slot = null,
        ?int $position = null,
    ): CmsBlock {
        return $this->create($owner, [
            'type' => SharedInsertionBlock::code(),
            'cms_shared_block_id' => $sharedBlock->getKey(),
            'name' => $sharedBlock->name,
        ], $parentId, $slot, $position);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function create(
        Model $owner,
        array $attributes,
        ?int $parentId,
        ?string $slot,
        ?int $position,
    ): CmsBlock {
        return DB::transaction(function () use ($owner, $attributes, $parentId, $slot, $position): CmsBlock {
            $block = new CmsBlock();
            $block->fill($attributes);
            $block->blockable_type = $owner->getMorphClass();
            $block->blockable_id = (int) ModelKey::of($owner);
            $block->parent_id = $parentId;
            $block->slot = $slot;
            $block->content = [];
            $block->is_active = true;
            // The position is decided by the move below, which renumbers the
            // whole group; the value written here never survives.
            $block->sort_order = 0;
            $block->save();

            return $this->move->handle($block, $parentId, $slot, $position);
        });
    }
}
