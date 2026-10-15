<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Rendering;

use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Blocks\Params\ResolvedParameters;

/**
 * The published blocks of one owner together with the parameters they were
 * rendered with.
 *
 * The two belong together because they come out of one traversal of one tree.
 * A resolver of a page needs both: the envelopes for the response and the
 * normalized parameters for the canonical address and the crawling rules. Ask
 * for them separately and the snapshot is hydrated twice and every options
 * provider of every filter goes to the database twice.
 *
 * The version comes along because the response of a resolver carries the date
 * of the publication beside the blocks.
 */
final readonly class RenderedBlocks
{
    /**
     * @param  list<array<string, mixed>>  $blocks  one envelope per root block
     */
    public function __construct(
        public array $blocks,
        public ResolvedParameters $parameters,
        public CmsVersion $version,
    ) {
    }
}
