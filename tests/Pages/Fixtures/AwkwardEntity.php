<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model of an installation that cannot be built out of nothing: an entity
 * assembled by a factory, a value object pulled in by the constructor. A stale
 * link naming such a class must not take the public resolver down with it, so
 * the resolver of entity links never instantiates the class it reads.
 */
final class AwkwardEntity extends Model
{
    public $timestamps = false;

    protected $table = 'test_awkward_entities';

    public function __construct(string $dependency)
    {
        parent::__construct(['slug' => $dependency]);
    }
}
