<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Concerns;

use Ewk\Cms\Seo\Models\CmsMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Attach to any publicly visible model: a page, a tour, an event, a venue, a
 * city, an artist. The relation is one to one — the pair of polymorphic columns
 * is unique.
 *
 * @phpstan-require-extends Model
 */
trait HasSeoMetadata
{
    /**
     * A record that is gone has no metadata. The row is deleted through its
     * model, so the picture it holds is released the way an edit releases it.
     * `deleted` and not `deleting`, so a failed delete leaves the row where it
     * was.
     *
     * A soft delete is not a delete: the record comes back and has to come
     * back with its metadata, so only the force delete removes the row.
     */
    public static function bootHasSeoMetadata(): void
    {
        static::deleted(static function (Model $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            CmsMetadata::query()
                ->where('model_type', $model->getMorphClass())
                ->where('model_id', $model->getKey())
                ->get()
                ->each(static fn (CmsMetadata $metadata): ?bool => $metadata->delete());
        });
    }

    /**
     * @return MorphOne<CmsMetadata, $this>
     */
    public function seoMetadata(): MorphOne
    {
        return $this->morphOne(CmsMetadata::class, 'model');
    }
}
