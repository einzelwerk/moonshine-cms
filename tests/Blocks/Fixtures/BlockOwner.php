<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\Concerns\HasBlocks;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property bool $is_active
 */
final class BlockOwner extends Model
{
    use HasBlocks;

    protected $table = 'test_block_owners';

    protected $fillable = ['id', 'is_active'];

    /** @var array<string, string> */
    protected $casts = ['is_active' => 'boolean'];
}
