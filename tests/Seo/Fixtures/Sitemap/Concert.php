<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Tests\Fixtures\Sitemap;

use Ewk\Cms\Seo\Concerns\HasSeoMetadata;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The subject of a template page type, the way an entity of an installation
 * looks to the sitemap: a slug, a parent in the address, a criterion of
 * public availability declared by name, metadata of its own and the moment
 * of its last edit.
 *
 * @property int $id
 * @property int $series_id
 * @property string $slug
 * @property bool $is_active
 * @property bool $is_hidden
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Series $series
 */
final class Concert extends Model
{
    use HasSeoMetadata;

    protected $table = 'test_sitemap_concerts';

    protected $fillable = ['series_id', 'slug', 'is_active', 'is_hidden'];

    /** @var array<string, string> */
    protected $casts = [
        'is_active' => 'boolean',
        'is_hidden' => 'boolean',
    ];

    /**
     * @return BelongsTo<Series, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * A hidden concert still opens: only the sitemap narrows it away.
     *
     * @param  Builder<Concert>  $query
     * @return Builder<Concert>
     */
    #[Scope]
    protected function publiclyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
