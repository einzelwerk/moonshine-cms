<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Concerns;

use Ewk\Cms\Blocks\Models\CmsBlock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Attach to any model that owns blocks: a page, an entity or a shared block
 * record. Every block belongs to an owner; rows without one are a data error.
 *
 * @phpstan-require-extends Model
 */
trait HasBlocks
{
    public static function bootHasBlocks(): void
    {
        // An owner that goes away takes its blocks with it. `blockable_type`
        // and `blockable_id` are polymorphic and carry no foreign key, so
        // nothing else does it, and the orphan rows would go on holding the
        // media of a page nobody can open any more.
        //
        // After the deletion and not before it: an owner may refuse to go —
        // a library entry that is still inserted somewhere — and must not
        // lose its content over an attempt.
        static::deleted(static function (self $owner): void {
            $owner->deleteOwnedBlocks();
        });
    }

    /**
     * Blocks in their editor-defined order: by position, then by key for rows
     * that share a position (seeded or imported data).
     *
     * @return MorphMany<CmsBlock, $this>
     */
    public function blocks(): MorphMany
    {
        return $this->morphMany(CmsBlock::class, 'blockable')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * One by one and never in a single `delete()` over the relation: the
     * release of what a block used hangs on the model event of that block, and
     * a mass delete fires none.
     *
     * The deepest rows go first, because `cms_blocks.parent_id` is a real
     * foreign key and a container deleted before its children is a row the
     * database refuses.
     */
    private function deleteOwnedBlocks(): void
    {
        /** @var list<CmsBlock> $blocks */
        $blocks = $this->blocks()->get()->all();

        $parents = [];

        foreach ($blocks as $block) {
            $parents[$block->id] = $block->parent_id;
        }

        usort(
            $blocks,
            static fn (CmsBlock $one, CmsBlock $other): int => self::depthOf($parents, $other->id)
                <=> self::depthOf($parents, $one->id),
        );

        foreach ($blocks as $block) {
            $block->delete();
        }
    }

    /**
     * How deep a row sits among the rows of this owner. A parent outside the
     * set — data the tree cannot explain — simply ends the walk.
     *
     * @param  array<int, int|null>  $parents  block key => parent key
     */
    private static function depthOf(array $parents, int $key): int
    {
        $depth = 0;
        $seen = [];

        while (isset($parents[$key]) && ! isset($seen[$key])) {
            $seen[$key] = true;
            $key = (int) $parents[$key];
            $depth++;
        }

        return $depth;
    }
}
