<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Http\Resolve;

use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Blocks\Rendering\BlockNode;
use Ewk\Cms\Blocks\Rendering\BlockTreeBuilder;

/**
 * The anchor of a block as the published snapshot holds it.
 *
 * The endpoint of one block is addressed by uuid, while its parameters are
 * namespaced by anchor, so the anchor has to be read before the parameters can
 * be parsed. It is read out of the snapshot and never out of the request: the
 * client would otherwise decide under which namespace his values land.
 */
final readonly class SnapshotAnchor
{
    public function __construct(private BlockTreeBuilder $trees)
    {
    }

    public function of(CmsVersion $version, string $uuid): ?string
    {
        return $this->find($this->trees->published($version), $uuid);
    }

    /**
     * @param  list<BlockNode>  $nodes
     */
    private function find(array $nodes, string $uuid): ?string
    {
        foreach ($nodes as $node) {
            if ($node->block->uuid === $uuid) {
                $anchor = $node->block->anchor;

                return $anchor === null || $anchor === '' ? null : $anchor;
            }

            foreach ($node->children as $children) {
                $found = $this->find($children, $uuid);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
