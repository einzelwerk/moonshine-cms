<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A target of an entity field: the block references it by type and key and
 * never by a resolved address.
 *
 * @property int $id
 * @property string $name
 */
final class Catalogue extends Model
{
    protected $table = 'test_catalogue';

    protected $fillable = ['id', 'name'];

    public $timestamps = false;
}
