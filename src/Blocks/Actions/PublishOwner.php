<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Actions;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Contracts\ContainerBlockContract;
use Ewk\Cms\Blocks\Contracts\DeclaresRequiredBlocks;
use Ewk\Cms\Blocks\Exceptions\PublicationRejectedException;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Blocks\Rendering\BlockNode;
use Ewk\Cms\Blocks\Rendering\BlockTreeBuilder;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Publishing an owner. The live rows stay the draft; this action writes the
 * snapshot the public response reads.
 *
 * Publication is the place where integrity is checked, because a draft must
 * save in any state: the required blocks of the owner are in place and active,
 * the minimums of the container slots are met, and no block carries a type
 * absent from the registry. A failed publication returns the list of what is
 * in the way and writes nothing.
 *
 * The snapshot stores the settings of the tree, not a rendered response, and
 * it stores references rather than expanded content: media and inserted shared
 * blocks resolve to their current published state on request, which is exactly
 * what the library exists for.
 */
final readonly class PublishOwner
{
    public function __construct(
        private BlockTreeBuilder $trees,
        private BlockRegistryInterface $registry,
        private LanguageCatalog $catalog,
    ) {
    }

    /**
     * @throws PublicationRejectedException
     */
    public function handle(Model $owner, ?string $note = null, ?int $authorId = null): CmsVersion
    {
        $problems = $this->problemsOf($owner);

        if ($problems !== []) {
            throw PublicationRejectedException::withProblems($problems);
        }

        $snapshot = [
            'owner' => $owner->attributesToArray(),
            'blocks' => $this->snapshotOf($owner),
        ];

        return DB::transaction(function () use ($owner, $snapshot, $note, $authorId): CmsVersion {
            $key = [
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
            ];

            $number = CmsVersion::query()->where($key)->first(['number'])->number ?? 0;

            return CmsVersion::query()->updateOrCreate($key, [
                'number' => $number + 1,
                'snapshot' => $snapshot,
                // The whole catalog is published at once; choosing languages
                // at publication arrives later without a migration.
                'locales' => $this->catalog->locales(),
                'note' => $note,
                'author_id' => $authorId,
                'published_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Everything standing in the way of a publication, in one report.
     *
     * @return list<string>
     */
    public function problemsOf(Model $owner): array
    {
        $tree = $this->trees->ownTree($owner);

        return [
            ...$this->unregisteredTypes($owner),
            ...$this->missingRequiredBlocks($owner, $tree),
            ...$this->unmetSlotMinimums($tree),
        ];
    }

    /**
     * @return list<string>
     */
    private function unregisteredTypes(Model $owner): array
    {
        $problems = [];
        $reported = [];

        foreach ($this->rowsOf($owner) as $row) {
            if (isset($reported[$row->type]) || $this->registry->has($row->type)) {
                continue;
            }

            $reported[$row->type] = true;
            $problems[] = "Block type [{$row->type}] is not registered.";
        }

        return $problems;
    }

    /**
     * @param  list<BlockNode>  $tree
     * @return list<string>
     */
    private function missingRequiredBlocks(Model $owner, array $tree): array
    {
        if (! $owner instanceof DeclaresRequiredBlocks) {
            return [];
        }

        $present = $this->typesOf($tree);
        $problems = [];

        foreach ($owner->requiredBlockCodes() as $code) {
            if (! isset($present[$code])) {
                $problems[] = "Required block [{$code}] is missing or switched off.";
            }
        }

        return $problems;
    }

    /**
     * The minimum number of children is checked here and nowhere else: an
     * intermediate empty slot must be saveable in a draft.
     *
     * @param  list<BlockNode>  $tree
     * @return list<string>
     */
    private function unmetSlotMinimums(array $tree): array
    {
        $problems = [];

        foreach ($tree as $node) {
            $definition = $this->registry->has($node->block->type)
                ? $this->registry->make($node->block->type)
                : null;

            if ($definition instanceof ContainerBlockContract) {
                foreach ($definition->slots($node->block->variant) as $slot) {
                    $children = count($node->children[$slot->name] ?? []);

                    if ($children < $slot->min) {
                        $problems[] = "Slot [{$slot->name}] of block [{$node->block->uuid}] "
                            ."needs at least {$slot->min} child block(s), has {$children}.";
                    }
                }
            }

            foreach ($node->children as $children) {
                $problems = [...$problems, ...$this->unmetSlotMinimums($children)];
            }
        }

        return $problems;
    }

    /**
     * @param  list<BlockNode>  $tree
     * @return array<string, true>
     */
    private function typesOf(array $tree): array
    {
        $types = [];

        foreach ($tree as $node) {
            $types[$node->block->type] = true;

            foreach ($node->children as $children) {
                $types = [...$types, ...$this->typesOf($children)];
            }
        }

        return $types;
    }

    /**
     * The snapshot keeps the tree as it lies in the database, switched off
     * rows included: pruning belongs to the response, so switching a block
     * back on does not require a new publication.
     *
     * @return list<array<string, mixed>>
     */
    private function snapshotOf(Model $owner): array
    {
        $byParent = [];

        foreach ($this->rowsOf($owner) as $row) {
            $byParent[$row->parent_id ?? 0][] = $row;
        }

        return $this->snapshotNodes(0, $byParent);
    }

    /**
     * @param  array<int, list<CmsBlock>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function snapshotNodes(int $parentId, array $byParent): array
    {
        $nodes = [];

        foreach ($byParent[$parentId] ?? [] as $row) {
            $children = [];

            foreach ($this->snapshotNodes($row->id, $byParent) as $child) {
                $slot = $child['slot'];

                if (is_string($slot) && $slot !== '') {
                    $children[$slot][] = $child;
                }
            }

            $nodes[] = [
                'uuid' => $row->uuid,
                'type' => $row->type,
                'variant' => $row->variant,
                'name' => $row->name,
                'anchor' => $row->anchor,
                'slot' => $row->slot,
                'content' => $row->content,
                'cms_shared_block_id' => $row->cms_shared_block_id,
                'sort_order' => $row->sort_order,
                'is_active' => $row->is_active,
                'children' => $children,
            ];
        }

        return $nodes;
    }

    /**
     * @return Collection<int, CmsBlock>
     */
    private function rowsOf(Model $owner): Collection
    {
        return CmsBlock::query()
            ->where('blockable_type', $owner->getMorphClass())
            ->where('blockable_id', $owner->getKey())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
