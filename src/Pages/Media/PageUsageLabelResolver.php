<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Media;

use Ewk\Cms\Blocks\Media\UsageOwnerCaption;
use Ewk\Cms\Media\Models\CmsMediaUsage;
use Ewk\Cms\Media\Usages\UsageLabelResolver;
use Ewk\Cms\Media\Usages\UsageOwnerLabel;
use Ewk\Cms\Pages\Models\CmsPage;

/**
 * Names a page in the list of «where this file is used».
 *
 * The path is the fallback and not the identifier: a page whose name nobody
 * filled in is still findable by its address, which is what an editor reads
 * in the tree anyway.
 *
 * No address of the admin is built here: this package knows the addresses of
 * the frontend and not the ones of the panel, so the registry hands the panel
 * the alias and the key and lets it build its own.
 */
final readonly class PageUsageLabelResolver implements UsageLabelResolver
{
    public function supports(string $usableType): bool
    {
        return $usableType === CmsPage::MORPH_ALIAS;
    }

    public function resolve(CmsMediaUsage $usage): ?UsageOwnerLabel
    {
        $page = $usage->usable;

        if (! $page instanceof CmsPage) {
            return null;
        }

        return new UsageOwnerLabel(
            title: UsageOwnerCaption::of($page->getAttribute('name'), $page->path),
            url: null,
            type: 'page',
            ownerType: CmsPage::MORPH_ALIAS,
            ownerId: $usage->usable_id,
        );
    }
}
