<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Sitemap;

use Ewk\Cms\Seo\Models\CmsMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * What an editor took out of the index stays out of the sitemap: a sitemap
 * lists the canonical addresses a site wants found, see Google's guidance on
 * building one.
 *
 * Two switches of the metadata row say "not this address": indexing turned
 * off, and a manual canonical address, which declares another address the
 * canonical one. An owner without a row is indexed, exactly as
 * {@see \Ewk\Cms\Seo\Documents\MetadataDocumentBuilder} answers its robots.
 *
 * Written into the query and not filtered afterwards: the feed cuts files by
 * count and offset, so the set has to be exact before it is cut.
 */
final class Indexable
{
    /**
     * The table of {@see CmsMetadata}, written out because a raw expression
     * below names it: the model declares no table of its own and the name is
     * derived from the class, see the CHANGELOG of 0.1.
     */
    private const string TABLE = 'cms_metadata';

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function apply(Builder $query): void
    {
        $model = $query->getModel();
        $table = self::TABLE;
        $morph = $model->getMorphClass();
        $key = $model->getQualifiedKeyName();

        $query->whereNotExists(static function (QueryBuilder $metadata) use ($table, $morph, $key): void {
            $metadata->selectRaw('1')
                ->from($table)
                ->where($table.'.model_type', $morph)
                ->whereColumn($table.'.model_id', $key)
                ->where(static function (QueryBuilder $excluded) use ($table): void {
                    $excluded->where($table.'.robots_index', false)
                        // A canonical address of blanks is no address, the way
                        // CanonicalUrl reads it.
                        ->orWhereRaw("TRIM(COALESCE(cms_metadata.canonical, '')) <> ''");
                });
        });
    }
}
