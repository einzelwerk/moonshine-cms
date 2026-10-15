<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stand-in for the catalog entity a nested address is built around; this
 * package knows no catalog of its own.
 *
 * @property int $id
 * @property string $slug
 */
final class Series extends Model
{
    public $timestamps = false;

    protected $table = 'test_series';

    protected $fillable = ['slug'];

    /**
     * @return HasMany<Concert, $this>
     */
    public function concerts(): HasMany
    {
        return $this->hasMany(Concert::class);
    }
}
