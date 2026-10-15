<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Http\Resources;

use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Params\NormalizedParameters;
use Ewk\Cms\Blocks\Params\ResolvedParameters;
use Illuminate\Http\Request;
use stdClass;

/**
 * The envelope of a block in the public answer. The core assembles `uuid`,
 * `code`, `variant`, `anchor` and `children`; only `data` belongs to the
 * concrete resource.
 *
 * The autoincrement id never leaves the backend: `uuid` is the stable
 * external identity and survives moving a block inside its owner.
 */
abstract class BlockResource
{
    protected ?NormalizedParameters $parameters = null;

    protected ?ResolvedParameters $resolvedParameters = null;

    public function __construct(protected readonly CmsBlock $block)
    {
    }

    /**
     * The renderer hands the resolved request parameters to a dynamic block
     * before it builds its payload. A block that is not dynamic is never given
     * any, so both readers stay `null` for it.
     */
    public function withResolvedParameters(
        ?NormalizedParameters $parameters,
        ResolvedParameters $resolved,
    ): static {
        $this->parameters = $parameters;
        $this->resolvedParameters = $resolved;

        return $this;
    }

    /**
     * The normalized set of this block: values cast to the declared types, the
     * page, the portion size and the position.
     */
    public function parameters(): ?NormalizedParameters
    {
        return $this->parameters;
    }

    /**
     * The sets of every dynamic block of the page. A list needs them to build
     * the address of its own next state without losing the parameters of its
     * neighbours.
     */
    public function resolvedParameters(): ?ResolvedParameters
    {
        return $this->resolvedParameters;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->block->uuid,
            'code' => $this->block->type,
            'variant' => $this->block->variant,
            'anchor' => $this->block->anchor,
            'data' => $this->toData($request),
            // Containers replace this with a map of their declared slots; every
            // other block keeps an empty map, so one recursive type describes
            // every block.
            //
            // An object and not an empty array: an empty PHP array encodes as
            // `[]`, and the frontend reads `children` as a map of slots, so a
            // block without slots has to answer `{}`.
            'children' => new stdClass(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function toData(Request $request): array;
}
