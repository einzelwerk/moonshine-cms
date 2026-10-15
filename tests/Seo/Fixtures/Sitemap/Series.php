<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Tests\Fixtures\Sitemap;

use Illuminate\Database\Eloquent\Model;

/**
 * The parent of a concert in its address: `/series/{series}/{concert}`.
 *
 * @property int $id
 * @property string $slug
 */
final class Series extends Model
{
    public $timestamps = false;

    protected $table = 'test_sitemap_series';

    protected $fillable = ['slug'];
}
