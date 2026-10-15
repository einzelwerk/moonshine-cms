<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Rendering;

use Ewk\Cms\Blocks\Concerns\HasBlocks;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Contracts\CollectsOwnerAnchors;
use Ewk\Cms\Blocks\Contracts\ContainerBlockContract;
use Ewk\Cms\Blocks\Contracts\OmitsWhenEmpty;
use Ewk\Cms\Blocks\Events\UnknownBlockTypeEncountered;
use Ewk\Cms\Blocks\Http\Resources\BlockResource;
use Ewk\Cms\Blocks\Localization\LocalizedValueResolver;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Blocks\Params\BlockParameters;
use Ewk\Cms\Blocks\Params\ParameterResolution;
use Ewk\Cms\Blocks\Params\ResolvedParameters;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use stdClass;

/**
 * Builds the response of an owner's blocks: one envelope per block, children
 * under `children` as a map by slot, in editor order.
 *
 * Two states are readable. {@see BlockRenderer::render()} reads the live rows,
 * which are the draft the editor works on. {@see
 * BlockRenderer::renderPublished()} reads the snapshot, which is what a visitor
 * gets; an owner without a snapshot, or a switched off one, has nothing to show
 * and answers `null` so the caller can turn it into a 404.
 *
 * A block whose type is missing from the registry is excluded from the
 * response and reported through {@see UnknownBlockTypeEncountered}; rendering
 * never fails because of stale data. A block that has nothing to show is
 * excluded as well, when its resource says so, see {@see OmitsWhenEmpty}.
 */
final readonly class BlockRenderer
{
    public function __construct(
        private BlockRegistryInterface $registry,
        private BlockTreeBuilder $trees,
        private Container $container,
        private Dispatcher $events,
        private LanguageCatalog $catalog,
        private Translator $translator,
        private ParameterResolution $resolution,
    ) {
    }

    /**
     * The draft: live rows, so the editor sees what he has just changed.
     *
     * @param  Model  $owner  a model using the {@see HasBlocks} trait
     * @return list<array<string, mixed>>
     */
    public function render(Model $owner, Request $request, ?BlockParameters $parameters = null): array
    {
        $nodes = $this->trees->draft($owner);

        return $this->envelopes($nodes, $request, $this->resolve($nodes, $parameters));
    }

    /**
     * The published state, or `null` when there is nothing published: no
     * snapshot means the owner is still a draft, and an inactive owner is taken
     * off publication while its snapshot is kept.
     *
     * @return list<array<string, mixed>>|null
     */
    public function renderPublished(Model $owner, Request $request, ?BlockParameters $parameters = null): ?array
    {
        return $this->renderPublishedDocument(
            $owner,
            $request,
            $parameters ?? BlockParameters::none(),
        )?->blocks;
    }

    /**
     * The published blocks and the parameters they were rendered with, out of
     * one traversal of one tree: a resolver of a page needs both, and asking
     * for them one after the other hydrates the snapshot twice and sends every
     * options provider of every filter to the database twice.
     *
     * `null` for the same reasons {@see BlockRenderer::renderPublished()}
     * answers `null`: no snapshot, or an owner taken off publication.
     *
     * A caller that has already found the snapshot — a resolver decides on the
     * publication before it writes a byte of the answer — hands it in, and the
     * table is not asked a second time for the row it just read.
     */
    public function renderPublishedDocument(
        Model $owner,
        Request $request,
        BlockParameters $parameters,
        ?CmsVersion $version = null,
    ): ?RenderedBlocks {
        $version ??= $this->publishedVersion($owner);

        if ($version === null) {
            return null;
        }

        $nodes = $this->trees->published($version);
        $resolved = $this->resolve($nodes, $parameters);

        return new RenderedBlocks(
            blocks: $this->envelopes($nodes, $request, $resolved),
            parameters: $resolved,
            version: $version,
        );
    }

    /**
     * The envelope of one published block, for the endpoint of the data of a
     * single block. The block is looked for in the snapshot of the owner and
     * never in the live rows: a visitor reads what is published.
     *
     * `null` covers every case the caller turns into a 404: no snapshot, a
     * switched off owner, a uuid that is not in this snapshot, a switched off
     * block, a block that is not dynamic and one that has nothing to show, see
     * {@see OmitsWhenEmpty}. A uuid of another page is therefore not an address
     * to pull an arbitrary block out of.
     *
     * @return array<string, mixed>|null
     */
    public function renderPublishedBlock(
        Model $owner,
        string $uuid,
        Request $request,
        BlockParameters $parameters,
    ): ?array {
        $nodes = $this->publishedNodes($owner);

        if ($nodes === null) {
            return null;
        }

        $node = $this->nodeOf($nodes, $uuid);

        if ($node === null) {
            return null;
        }

        if (! $this->registry->has($node->block->type)) {
            $this->events->dispatch(UnknownBlockTypeEncountered::forBlock($node->block));

            return null;
        }

        if (! $this->registry->schemaFor($node->block->type)->isDynamic()) {
            return null;
        }

        return $this->envelopeOf(
            $node,
            $request,
            $this->anchorsOf($nodes),
            $this->resolve($nodes, $parameters),
        );
    }

    /**
     * The parameters allowed by the blocks of one owner, without rendering
     * anything: the resolver of a page needs them for the canonical address and
     * for the crawling rules before a single payload is built.
     *
     * The tree is read from the very same source the matching render reads, so
     * the two never disagree about which blocks exist.
     */
    public function parametersOf(Model $owner, BlockParameters $raw, bool $published = true): ResolvedParameters
    {
        $nodes = $published ? $this->publishedNodes($owner) : $this->trees->draft($owner);

        return $this->resolution->resolve($this->flatten($nodes ?? []), $raw);
    }

    /**
     * @return list<BlockNode>|null
     */
    private function publishedNodes(Model $owner): ?array
    {
        $version = $this->publishedVersion($owner);

        return $version === null ? null : $this->trees->published($version);
    }

    /**
     * The snapshot a visitor may read: none while the owner is still a draft,
     * and none while it is taken off publication.
     */
    private function publishedVersion(Model $owner): ?CmsVersion
    {
        $active = $owner->getAttribute('is_active');

        if ($active !== null && ! $active) {
            return null;
        }

        return $this->versionOf($owner);
    }

    public function versionOf(Model $owner): ?CmsVersion
    {
        return CmsVersion::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->first();
    }

    /**
     * @param  list<BlockNode>  $nodes
     * @return list<array<string, mixed>>
     */
    private function envelopes(array $nodes, Request $request, ResolvedParameters $resolved): array
    {
        // The anchors of the whole owner are known before a single envelope is
        // built, because the tree is already assembled: a table of contents
        // describes the page, not its own slot.
        return $this->build($nodes, $request, $this->anchorsOf($nodes), $resolved);
    }

    /**
     * @param  list<BlockNode>  $nodes
     */
    private function resolve(array $nodes, ?BlockParameters $parameters): ResolvedParameters
    {
        return $this->resolution->resolve(
            $this->flatten($nodes),
            $parameters ?? BlockParameters::none(),
        );
    }

    /**
     * The tree walked flat, children of containers and inserted shared blocks
     * included: a list inside a column takes its parameters exactly like a root
     * one.
     *
     * @param  list<BlockNode>  $nodes
     * @return list<CmsBlock>
     */
    private function flatten(array $nodes): array
    {
        $blocks = [];

        foreach ($nodes as $node) {
            $blocks[] = $node->block;

            foreach ($node->children as $children) {
                foreach ($this->flatten($children) as $nested) {
                    $blocks[] = $nested;
                }
            }
        }

        return $blocks;
    }

    /**
     * @param  list<BlockNode>  $nodes
     */
    private function nodeOf(array $nodes, string $uuid): ?BlockNode
    {
        foreach ($nodes as $node) {
            if ($node->block->uuid === $uuid) {
                return $node;
            }

            foreach ($node->children as $children) {
                $found = $this->nodeOf($children, $uuid);

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<BlockNode>  $nodes
     * @param  list<array{uuid: string, anchor: string, title: string}>  $anchors
     * @return list<array<string, mixed>>
     */
    private function build(array $nodes, Request $request, array $anchors, ResolvedParameters $resolved): array
    {
        $built = [];

        foreach ($nodes as $node) {
            $envelope = $this->envelopeOf($node, $request, $anchors, $resolved);

            if ($envelope !== null) {
                $built[] = $envelope;
            }
        }

        return $built;
    }

    /**
     * @param  list<array{uuid: string, anchor: string, title: string}>  $anchors
     * @return array<string, mixed>|null
     */
    private function envelopeOf(
        BlockNode $node,
        Request $request,
        array $anchors,
        ResolvedParameters $resolved,
    ): ?array {
        $block = $node->block;

        if (! $this->registry->has($block->type)) {
            $this->events->dispatch(UnknownBlockTypeEncountered::forBlock($block));

            return null;
        }

        $definition = $this->registry->make($block->type);
        $schema = $definition->schema();

        /** @var BlockResource $resource */
        $resource = $this->container->make($definition->resource(), [
            'block' => $block,
            'schema' => $schema,
        ]);

        if ($schema->isDynamic()) {
            $anchor = $block->anchor;

            $resource = $resource->withResolvedParameters(
                $anchor === null || $anchor === '' ? null : $resolved->for($anchor),
                $resolved,
            );
        }

        if ($resource instanceof CollectsOwnerAnchors) {
            $resource = $resource->withCollectedAnchors($this->anchorsExcept($anchors, $block->uuid));
        }

        $envelope = $resource->toArray($request);

        if ($resource instanceof OmitsWhenEmpty && is_array($envelope['data']) && $resource->isEmpty($envelope['data'], $request)) {
            return null;
        }

        if ($definition instanceof ContainerBlockContract) {
            $envelope['children'] = $this->slotsOf($definition, $node, $request, $anchors, $resolved);
        }

        return $envelope;
    }

    /**
     * Every declared slot is present in the response, empty ones included, and
     * they follow the order of the schema. Children stored in a slot the
     * current variant no longer declares stay in the database and out of the
     * payload.
     *
     * @param  list<array{uuid: string, anchor: string, title: string}>  $anchors
     * @return array<string, list<array<string, mixed>>>|stdClass
     */
    private function slotsOf(
        ContainerBlockContract $definition,
        BlockNode $node,
        Request $request,
        array $anchors,
        ResolvedParameters $resolved,
    ): array|stdClass {
        $slots = [];

        foreach ($definition->slots($node->block->variant) as $slot) {
            $slots[$slot->name] = $this->build(
                $node->children[$slot->name] ?? [],
                $request,
                $anchors,
                $resolved,
            );
        }

        // A variant that declares no slots is still a container, and `children`
        // stays a map: an empty PHP array would encode as `[]`, see
        // {@see BlockResource::toArray()}.
        return $slots === [] ? new stdClass() : $slots;
    }

    /**
     * Anchors of the whole tree in editor order, depth first: a nested block
     * belongs to the page as much as a root one.
     *
     * @param  list<BlockNode>  $nodes
     * @return list<array{uuid: string, anchor: string, title: string}>
     */
    private function anchorsOf(array $nodes): array
    {
        $anchors = [];

        foreach ($nodes as $node) {
            $anchor = $node->block->anchor;

            if ($anchor !== null && $anchor !== '') {
                $anchors[] = [
                    'uuid' => $node->block->uuid,
                    'anchor' => $anchor,
                    'title' => $this->titleOf($node->block),
                ];
            }

            foreach ($node->children as $children) {
                foreach ($this->anchorsOf($children) as $nested) {
                    $anchors[] = $nested;
                }
            }
        }

        return $anchors;
    }

    /**
     * @param  list<array{uuid: string, anchor: string, title: string}>  $anchors
     * @return list<array{anchor: string, title: string}>
     */
    private function anchorsExcept(array $anchors, string $uuid): array
    {
        $collected = [];

        foreach ($anchors as $anchor) {
            if ($anchor['uuid'] === $uuid) {
                continue;
            }

            $collected[] = ['anchor' => $anchor['anchor'], 'title' => $anchor['title']];
        }

        return $collected;
    }

    /**
     * A human readable caption for an anchor: the resolved title of the block
     * when it has one, otherwise its admin label.
     */
    private function titleOf(CmsBlock $block): string
    {
        $content = $block->content ?? [];
        $title = $content['title'] ?? null;

        $resolved = is_array($title) || is_string($title)
            ? LocalizedValueResolver::resolve($title, $this->translator->getLocale(), $this->catalog->defaultLocale())
            : '';

        return $resolved === '' ? $block->name : $resolved;
    }
}
