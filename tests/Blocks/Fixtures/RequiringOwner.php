<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Tests\Fixtures;

use Ewk\Cms\Blocks\Concerns\HasBlocks;
use Ewk\Cms\Blocks\Contracts\DeclaresRequiredBlocks;
use Illuminate\Database\Eloquent\Model;

/**
 * An owner that requires block types, the way a page requires the blocks of
 * its page type.
 *
 * @property int $id
 * @property bool $is_active
 */
final class RequiringOwner extends Model implements DeclaresRequiredBlocks
{
    use HasBlocks;

    protected $table = 'test_block_owners';

    protected $fillable = ['id', 'is_active'];

    /** @var array<string, string> */
    protected $casts = ['is_active' => 'boolean'];

    /** @var list<string> */
    public array $required = [];

    public function requiredBlockCodes(): array
    {
        return $this->required;
    }
}
