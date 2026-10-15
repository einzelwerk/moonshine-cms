<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Sitemap;

use DateTimeInterface;
use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Seo\Models\CmsMetadata;

/**
 * The moments the date of a sitemap entry is built from, read for one slice
 * at a time: one query per kind and not one per record.
 *
 * Only what a visitor sees counts: the publication of a snapshot and not every
 * save of the draft, an edit of the metadata because it changes the head of the
 * page.
 */
final class Moments
{
    /**
     * When the metadata row of every owner was last saved.
     *
     * @param  list<int|string>  $ids
     * @return array<int|string, DateTimeInterface>
     */
    public static function metadata(string $morph, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $moments = [];

        $rows = CmsMetadata::query()
            ->where('model_type', $morph)
            ->whereIn('model_id', $ids)
            ->get(['model_id', 'updated_at']);

        foreach ($rows as $row) {
            if ($row->updated_at !== null) {
                $moments[$row->model_id] = $row->updated_at;
            }
        }

        return $moments;
    }

    /**
     * When the snapshot of every owner was published.
     *
     * @param  list<int|string>  $ids
     * @return array<int|string, DateTimeInterface>
     */
    public static function publications(string $morph, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $moments = [];

        $rows = CmsVersion::query()
            ->where('owner_type', $morph)
            ->whereIn('owner_id', $ids)
            ->get(['owner_id', 'published_at']);

        foreach ($rows as $row) {
            if ($row->published_at !== null) {
                $moments[$row->owner_id] = $row->published_at;
            }
        }

        return $moments;
    }
}
