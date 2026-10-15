<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Rendering;

use Ewk\Cms\Blocks\Blocks\SharedInsertionBlock;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Models\CmsVersion;
use Illuminate\Database\Eloquent\Model;

/**
 * Assembles the tree of an owner's blocks in memory: one query for every row,
 * then the tree, then the pruning. Activity cannot be filtered in SQL — the
 * children of a switched off container would surface as orphans.
 *
 * The same assembler serves the draft and the published state. A snapshot holds
 * the rows as they lay in the database, so hydrating it produces the very same
 * nodes and the renderer above stays one code path.
 */
final class BlockTreeBuilder
{
    /**
     * Live rows: what the editor sees, including the blocks he has not
     * published yet.
     *
     * @return list<BlockNode>
     */
    public function draft(Model $owner): array
    {
        return $this->fromRows(
            $this->rowsOf($owner->getMorphClass(), $owner->getKey()),
            BlockTreeMode::DRAFT,
        );
    }

    /**
     * Published state: the snapshot of the owner, with references resolved
     * against the current published state of what they point at.
     *
     * @return list<BlockNode>
     */
    public function published(CmsVersion $version): array
    {
        return $this->fromSnapshot(
            $version->snapshotBlocks(),
            $version->owner_type,
            $version->owner_id,
            BlockTreeMode::PUBLISHED,
        );
    }

    /**
     * The owner's own live tree with insertions left alone: publication checks
     * the slots of the containers of this owner, not of the library entries it
     * merely points at.
     *
     * @return list<BlockNode>
     */
    public function ownTree(Model $owner): array
    {
        return $this->fromRows(
            $this->rowsOf($owner->getMorphClass(), $owner->getKey()),
            BlockTreeMode::OWN,
        );
    }

    /**
     * @param  list<CmsBlock>  $rows
     * @return list<BlockNode>
     */
    private function fromRows(array $rows, BlockTreeMode $mode): array
    {
        $byParent = [];

        foreach ($rows as $row) {
            $byParent[$row->parent_id ?? 0][] = $row;
        }

        return $this->nodesOf(0, $byParent, $mode);
    }

    /**
     * @param  array<int, list<CmsBlock>>  $byParent
     * @return list<BlockNode>
     */
    private function nodesOf(int $parentId, array $byParent, BlockTreeMode $mode): array
    {
        $nodes = [];

        foreach ($byParent[$parentId] ?? [] as $row) {
            // A switched off block takes its whole subtree with it: effective
            // activity is an AND along the chain of ancestors.
            if (! $row->is_active) {
                continue;
            }

            $node = $row->type === SharedInsertionBlock::code()
                ? $this->insertionNode($row, $mode)
                : new BlockNode($row, $this->childrenOf(
                    $this->nodesOf($row->id, $byParent, $mode),
                ));

            if ($node !== null) {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<BlockNode>
     */
    private function fromSnapshot(array $nodes, string $ownerType, int|string $ownerId, BlockTreeMode $mode): array
    {
        $assembled = [];

        foreach ($nodes as $node) {
            if (! ($node['is_active'] ?? true)) {
                continue;
            }

            $block = $this->hydrate($node, $ownerType, $ownerId);

            if ($block->type === SharedInsertionBlock::code()) {
                $insertion = $this->insertionNode($block, $mode);

                if ($insertion !== null) {
                    $assembled[] = $insertion;
                }

                continue;
            }

            $children = [];
            $stored = $node['children'] ?? [];

            if (is_array($stored)) {
                foreach ($stored as $slot => $slotNodes) {
                    if (! is_array($slotNodes)) {
                        continue;
                    }

                    /** @var array<array-key, array<string, mixed>> $slotNodes */
                    $children[(string) $slot] = $this->fromSnapshot(
                        array_values($slotNodes),
                        $ownerType,
                        $ownerId,
                        $mode,
                    );
                }
            }

            $assembled[] = new BlockNode($block, array_filter($children, static fn (array $n): bool => $n !== []));
        }

        return $assembled;
    }

    /**
     * The renderer substitutes the tree of the original in place of the
     * insertion. Activity combines through AND, and an insertion of a library
     * entry that is switched off, empty or not published yet drops out of the
     * response just like a switched off block would.
     */
    private function insertionNode(CmsBlock $insertion, BlockTreeMode $mode): ?BlockNode
    {
        if ($mode === BlockTreeMode::OWN || $insertion->cms_shared_block_id === null) {
            return new BlockNode($insertion);
        }

        $shared = CmsSharedBlock::query()->find($insertion->cms_shared_block_id);

        if ($shared === null || ! $shared->is_active) {
            return null;
        }

        $root = $mode === BlockTreeMode::PUBLISHED
            ? $this->publishedRootOf($shared)
            : $this->draftRootOf($shared);

        if ($root === null) {
            return null;
        }

        return new BlockNode($this->insertionView($insertion, $root->block), $root->children);
    }

    private function draftRootOf(CmsSharedBlock $shared): ?BlockNode
    {
        $roots = $this->fromRows(
            $this->rowsOf($shared->getMorphClass(), $shared->getKey()),
            // A shared block never holds an insertion of another shared
            // block, so no further resolution can occur here.
            BlockTreeMode::OWN,
        );

        return $roots[0] ?? null;
    }

    private function publishedRootOf(CmsSharedBlock $shared): ?BlockNode
    {
        $version = CmsVersion::query()
            ->where('owner_type', $shared->getMorphClass())
            ->where('owner_id', $shared->getKey())
            ->first();

        if ($version === null) {
            return null;
        }

        $roots = $this->fromSnapshot(
            $version->snapshotBlocks(),
            $shared->getMorphClass(),
            $shared->id,
            BlockTreeMode::OWN,
        );

        return $roots[0] ?? null;
    }

    /**
     * The envelope of an insertion carries the content of the original and the
     * identity of the insertion: the uuid, the anchor and the position belong
     * to the insertion, so two placements of one banner stay addressable
     * apart.
     */
    private function insertionView(CmsBlock $insertion, CmsBlock $original): CmsBlock
    {
        $attributes = $original->getAttributes();

        $attributes['id'] = $insertion->getKey();
        $attributes['uuid'] = $insertion->uuid;
        $attributes['anchor'] = $insertion->anchor;
        $attributes['blockable_type'] = $insertion->blockable_type;
        $attributes['blockable_id'] = $insertion->blockable_id;
        $attributes['parent_id'] = $insertion->parent_id;
        $attributes['slot'] = $insertion->slot;
        $attributes['sort_order'] = $insertion->sort_order;
        $attributes['cms_shared_block_id'] = $insertion->cms_shared_block_id;

        return (new CmsBlock())->newFromBuilder($attributes);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function hydrate(array $node, string $ownerType, int|string $ownerId): CmsBlock
    {
        $content = $node['content'] ?? null;

        return (new CmsBlock())->newFromBuilder([
            'uuid' => self::text($node['uuid'] ?? null),
            'blockable_type' => $ownerType,
            'blockable_id' => $ownerId,
            'slot' => isset($node['slot']) ? self::text($node['slot']) : null,
            'type' => self::text($node['type'] ?? null),
            'variant' => isset($node['variant']) ? self::text($node['variant']) : null,
            'name' => self::text($node['name'] ?? null),
            'anchor' => isset($node['anchor']) ? self::text($node['anchor']) : null,
            // The cast reads a json string, the way a row arrives from the
            // database.
            'content' => is_array($content) ? json_encode($content) : null,
            'cms_shared_block_id' => $node['cms_shared_block_id'] ?? null,
            'sort_order' => is_numeric($node['sort_order'] ?? null) ? (int) $node['sort_order'] : 0,
            'is_active' => (bool) ($node['is_active'] ?? true),
        ]);
    }

    /**
     * @param  list<BlockNode>  $nodes
     * @return array<string, list<BlockNode>>
     */
    private function childrenOf(array $nodes): array
    {
        $children = [];

        foreach ($nodes as $node) {
            $slot = $node->block->slot;

            if ($slot === null || $slot === '') {
                continue;
            }

            $children[$slot][] = $node;
        }

        return $children;
    }

    /**
     * @return list<CmsBlock>
     */
    private function rowsOf(string $ownerType, mixed $ownerId): array
    {
        $rows = CmsBlock::query()
            ->where('blockable_type', $ownerType)
            ->where('blockable_id', $ownerId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return array_values($rows->all());
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
