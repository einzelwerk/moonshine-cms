<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * The published state of an owner: the live rows are the draft, and the public
 * response reads this snapshot instead of them.
 *
 * The snapshot holds the settings of the blocks as they lie in the database,
 * not a rendered response: rendering happens on request, so live data
 * (schedules, prices, media, inserted shared blocks) is never frozen.
 *
 * One row per owner in this version: publishing overwrites it and raises the
 * number, so no history accumulates and no retention is needed.
 *
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property int $number
 * @property array<string, mixed> $snapshot
 * @property list<string> $locales
 * @property string|null $note
 * @property int|null $author_id
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $owner
 */
final class CmsVersion extends Model
{
    /** @see CmsBlock::MORPH_ALIAS */
    public const string MORPH_ALIAS = 'cms_version';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'number',
        'snapshot',
        'locales',
        'note',
        'author_id',
        'published_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'number' => 'integer',
        'snapshot' => 'array',
        'locales' => 'array',
        'author_id' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Block tree of the snapshot, root nodes in editor order.
     *
     * @return list<array<string, mixed>>
     */
    public function snapshotBlocks(): array
    {
        $blocks = $this->snapshot['blocks'] ?? [];

        /** @var list<array<string, mixed>> $nodes */
        $nodes = is_array($blocks) ? array_values($blocks) : [];

        return $nodes;
    }
}
