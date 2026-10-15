<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * An owner that neither uses the trait nor declares fallbacks: the builder
 * has to find its row by the polymorphic pair and to leave the title empty
 * rather than invent one.
 */
final class PlainOwner extends Model
{
    protected $table = 'test_seo_owners';

    protected $fillable = ['name', 'cover_url'];

    /** @var array<string, string> */
    protected $casts = ['name' => 'array'];
}
