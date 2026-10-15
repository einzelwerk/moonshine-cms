<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Models;

use Ewk\Cms\Blocks\Blocks\SharedInsertionBlock;
use Ewk\Cms\Blocks\Concerns\HasBlocks;
use Ewk\Cms\Blocks\Exceptions\SharedBlockInUseException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A block of the library: it lies once and shows on many pages. It is an entity
 * of its own, not a row inside a page, so it owns its blocks and therefore gets
 * the draft, the publication and the statuses of any other owner for free.
 *
 * Its content is ordinary block rows owned by this record, with exactly one
 * root block; a composite section uses a container as that root.
 *
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CmsBlock> $blocks
 * @property-read Collection<int, CmsBlock> $insertions
 */
final class CmsSharedBlock extends Model
{
    use HasBlocks;

    /** @see CmsBlock::MORPH_ALIAS */
    public const string MORPH_ALIAS = 'cms_shared_block';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (CmsSharedBlock $sharedBlock): void {
            $sharedBlock->uuid = $sharedBlock->uuid ?: (string) Str::uuid();
        });

        static::deleting(function (CmsSharedBlock $sharedBlock): void {
            $insertions = $sharedBlock->insertions()->count();

            if ($insertions > 0) {
                throw SharedBlockInUseException::forSharedBlock($sharedBlock, $insertions);
            }
        });
    }

    /**
     * Rows of type `shared` pointing at this record: where the library entry
     * is used. Deleting the original while these live is refused.
     *
     * @return HasMany<CmsBlock, $this>
     */
    public function insertions(): HasMany
    {
        return $this->hasMany(CmsBlock::class)
            ->where('type', SharedInsertionBlock::code());
    }

    /**
     * The single root block of the library entry, or null while it is empty.
     */
    public function rootBlock(): ?CmsBlock
    {
        return $this->blocks()->whereNull('parent_id')->first();
    }
}
