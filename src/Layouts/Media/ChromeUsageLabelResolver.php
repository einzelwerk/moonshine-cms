<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Media;

use Ewk\Cms\Blocks\Media\UsageOwnerCaption;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Media\Models\CmsMediaUsage;
use Ewk\Cms\Media\Usages\UsageLabelResolver;
use Ewk\Cms\Media\Usages\UsageOwnerLabel;

/**
 * Names a header or a footer, which hold a logo and payment icons the same way
 * a block holds a picture.
 *
 * The owner of such a usage is the header itself and never the layout above
 * it: a layout points at a header, it does not hold its pictures, so the
 * header is the record an editor has to open.
 */
final readonly class ChromeUsageLabelResolver implements UsageLabelResolver
{
    /** @var array<string, string> morph alias => the key the panel translates */
    private const array TYPES = [
        CmsHeader::MORPH_ALIAS => 'header',
        CmsFooter::MORPH_ALIAS => 'footer',
    ];

    public function supports(string $usableType): bool
    {
        return isset(self::TYPES[$usableType]);
    }

    public function resolve(CmsMediaUsage $usage): ?UsageOwnerLabel
    {
        $chrome = $usage->usable;

        if (! $chrome instanceof CmsHeader && ! $chrome instanceof CmsFooter) {
            return null;
        }

        $alias = $usage->usable_type;

        return new UsageOwnerLabel(
            title: UsageOwnerCaption::of($chrome->getAttribute('name'), $alias.' #'.$usage->usable_id),
            url: null,
            type: self::TYPES[$alias],
            ownerType: $alias,
            ownerId: $usage->usable_id,
        );
    }
}
