<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tests\Fixtures;

use Ewk\Cms\Blocks\Concerns\HasBlocks;
use Ewk\Cms\Blocks\Contracts\DeclaresRequiredBlocks;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal owner of blocks for the editor tests: the package itself ships no
 * owner model, a page or an entity of the consuming application does.
 *
 * The required codes are stored and not a property of the instance: the
 * handlers of the editor load the owner from the database inside the request,
 * exactly as a page is loaded by its resource.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property array<array-key, mixed>|null $required_blocks
 */
final class EditorOwner extends Model implements DeclaresRequiredBlocks
{
    use HasBlocks;

    protected $table = 'test_editor_owners';

    protected $fillable = ['id', 'name', 'is_active', 'required_blocks'];

    /** @var array<string, string> */
    protected $casts = ['is_active' => 'boolean', 'required_blocks' => 'array'];

    public function requiredBlockCodes(): array
    {
        return array_values(array_filter($this->required_blocks ?? [], 'is_string'));
    }
}
