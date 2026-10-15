<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Media;

use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Media\Models\CmsMediaUsage;
use Ewk\Cms\Media\Usages\EagerLoadsUsageOwners;
use Ewk\Cms\Media\Usages\UsageLabelRegistry;
use Ewk\Cms\Media\Usages\UsageLabelResolver;
use Ewk\Cms\Media\Usages\UsageOwnerLabel;

/**
 * Names the record a block belongs to.
 *
 * A usage of a block is written against the block, because that is the record
 * an editor opens to change it. What the list has to show, though, is the page
 * or the library entry the block sits on: «used by block #418» sends nobody
 * anywhere. So this resolver walks one step up and asks the registry to name
 * the owner, which is how a kind of owner added later needs no change here.
 *
 * A library entry is the one owner it names itself, because the blocks
 * package is where that entry lives.
 */
final readonly class BlockUsageLabelResolver implements EagerLoadsUsageOwners, UsageLabelResolver
{
    public function __construct(
        private UsageLabelRegistry $registry,
    ) {
    }

    public function supports(string $usableType): bool
    {
        return $usableType === CmsBlock::MORPH_ALIAS;
    }

    /**
     * The record a block sits on, loaded with the blocks and not once per
     * block: a picture used on twenty blocks would otherwise cost twenty
     * `select * from cms_pages where id = ?`.
     */
    public function eagerLoads(string $usableType): array
    {
        return ['blockable'];
    }

    public function resolve(CmsMediaUsage $usage): ?UsageOwnerLabel
    {
        $block = $usage->usable;

        if (! $block instanceof CmsBlock) {
            return null;
        }

        $ownerType = $block->blockable_type;
        $ownerId = $block->blockable_id;

        if ($ownerType === CmsSharedBlock::MORPH_ALIAS) {
            $sharedBlock = $block->blockable;

            return new UsageOwnerLabel(
                title: $sharedBlock instanceof CmsSharedBlock
                    ? UsageOwnerCaption::of($sharedBlock->getAttribute('name'), $sharedBlock->code)
                    : CmsSharedBlock::MORPH_ALIAS.' #'.$ownerId,
                url: null,
                type: 'shared_block',
                ownerType: CmsSharedBlock::MORPH_ALIAS,
                ownerId: $ownerId,
            );
        }

        // The owner is handed over when it is already in memory, so that
        // naming it costs nothing; the registry falls back to reading it when
        // the caller did not eager load.
        return $this->registry->for(
            $ownerType,
            $ownerId,
            $block->relationLoaded('blockable') ? $block->blockable : null,
        );
    }
}
