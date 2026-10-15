<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests\Fixtures;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The subject of a template page type: it has a slug, a parent in the
 * address and its own criterion of public availability, declared by name.
 *
 * @property int $id
 * @property int $series_id
 * @property string $slug
 * @property bool $is_active
 * @property bool $is_hidden
 * @property-read Series $series
 *
 * @method static Builder<static> publiclyVisible()
 */
final class Concert extends Model
{
    public $timestamps = false;

    protected $table = 'test_concerts';

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
     * @param  Builder<Concert>  $query
     * @return Builder<Concert>
     */
    #[Scope]
    protected function publiclyVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('is_hidden', false);
    }
}
