<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Support;

use Ewk\Cms\Blocks\Contracts\DeclaresRequiredBlocks;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The block an owner may not lose. The rule is checked in three places, because
 * any one of them alone is bypassed: at assembly, in the form, and here — on
 * the server, in the handlers of the editor, because deleting and switching off
 * go through their own requests and a hidden button is only a hint.
 *
 * Deactivation counts as removal: leaving the row in place while it stops
 * rendering is the same loss by another route, so both questions are answered
 * by the same predicate over the same set of rows the editor tree counts.
 */
final readonly class RequiredBlockGuard
{
    /**
     * Whether deleting this block would leave the owner without a code its
     * declaration demands.
     */
    public function protectsRemoval(CmsBlock $block, ?Model $owner = null): bool
    {
        return $this->isRequired($block, $owner) && $this->countOf($block, activeOnly: false) <= 1;
    }

    /**
     * Whether switching this block off would leave the owner without an active
     * block of a code its declaration demands.
     */
    public function protectsDeactivation(CmsBlock $block, ?Model $owner = null): bool
    {
        return $this->isRequired($block, $owner) && $this->countOf($block, activeOnly: true) <= 1;
    }

    /**
     * An owner that declares nothing — the usual case outside pages — protects
     * nothing.
     */
    private function isRequired(CmsBlock $block, ?Model $owner): bool
    {
        $owner ??= $block->blockable;

        if (! $owner instanceof DeclaresRequiredBlocks) {
            return false;
        }

        return in_array($block->type, $owner->requiredBlockCodes(), true);
    }

    /**
     * Instances of the code inside one owner, at any depth: the same set the
     * editor tree counts, so the hint and the refusal never disagree.
     */
    private function countOf(CmsBlock $block, bool $activeOnly): int
    {
        /** @var Builder<CmsBlock> $query */
        $query = CmsBlock::query()
            ->where('blockable_type', $block->blockable_type)
            ->where('blockable_id', $block->blockable_id)
            ->where('type', $block->type);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->count();
    }
}
