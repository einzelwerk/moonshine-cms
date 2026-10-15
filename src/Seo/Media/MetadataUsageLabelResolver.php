<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Media;

use Ewk\Cms\Media\Models\CmsMediaUsage;
use Ewk\Cms\Media\Usages\UsageLabelRegistry;
use Ewk\Cms\Media\Usages\UsageLabelResolver;
use Ewk\Cms\Media\Usages\UsageOwnerLabel;
use Ewk\Cms\Seo\Models\CmsMetadata;

/**
 * Names the card a picture of Open Graph or of Twitter belongs to.
 *
 * A metadata row has no name of its own and never will: it describes another
 * record, and «метаданные #12» would send an editor nowhere. So the record it
 * describes is named through the registry, and the kind stays `metadata`, so
 * that the panel can still say which part of that record holds the picture.
 */
final readonly class MetadataUsageLabelResolver implements UsageLabelResolver
{
    public function __construct(
        private UsageLabelRegistry $registry,
    ) {
    }

    public function supports(string $usableType): bool
    {
        return $usableType === CmsMetadata::MORPH_ALIAS;
    }

    public function resolve(CmsMediaUsage $usage): ?UsageOwnerLabel
    {
        $metadata = $usage->usable;

        if (! $metadata instanceof CmsMetadata) {
            return null;
        }

        $ownerType = $metadata->model_type;
        $ownerId = $metadata->model_id;

        // Metadata of metadata is not a thing the schema forbids, and asking
        // the registry about it would walk in a circle.
        if ($ownerType === CmsMetadata::MORPH_ALIAS) {
            return null;
        }

        return new UsageOwnerLabel(
            title: $this->registry->for($ownerType, $ownerId)->title,
            url: null,
            type: 'metadata',
            ownerType: $ownerType,
            ownerId: $ownerId,
        );
    }
}
