<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Models;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Media\ContentMediaUsages;
use Ewk\Cms\Blocks\Nesting\PlacementValidator;
use Ewk\Cms\Blocks\Support\ContentFilter;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One row per block: a block is an entity with its own identity, so it can be
 * addressed, referenced and reordered.
 *
 * @property int $id
 * @property string $uuid
 * @property string $blockable_type
 * @property int $blockable_id
 * @property int|null $parent_id
 * @property string|null $slot
 * @property string $type
 * @property string|null $variant
 * @property string $name
 * @property string|null $anchor
 * @property array<array-key, mixed>|null $content
 * @property int|null $cms_shared_block_id
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $blockable
 * @property-read CmsBlock|null $parent
 * @property-read Collection<int, CmsBlock> $children
 * @property-read CmsSharedBlock|null $cmsSharedBlock
 */
final class CmsBlock extends Model
{
    /**
     * The name usages are grouped under. A block holds one bag of content, so
     * one group covers it: the dotted path inside the bag is what tells two
     * pictures of one block apart.
     */
    public const string MEDIA_USAGE_FIELD = 'content';

    /**
     * What the polymorphic columns store for a block. An alias instead of the
     * class name keeps the rows readable and survives a renaming of the model.
     */
    public const string MORPH_ALIAS = 'cms_block';

    protected $fillable = [
        'uuid',
        'blockable_type',
        'blockable_id',
        'parent_id',
        'slot',
        'type',
        'variant',
        'name',
        'anchor',
        'content',
        'cms_shared_block_id',
        'sort_order',
        'is_active',
    ];

    /**
     * Every operation over a block marks its owner as changed: the four
     * statuses are computed out of the snapshot and that mark, so saving,
     * moving and deleting a block must all move it. Eloquent touches the owner
     * on a save and on a delete, which is exactly the set of operations that
     * must move it — a move is a save of the row.
     *
     * @var list<string>
     */
    protected $touches = ['blockable'];

    /** @var array<string, string> */
    protected $casts = [
        'content' => 'array',
        'is_active' => 'boolean',
        'parent_id' => 'integer',
        'cms_shared_block_id' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (CmsBlock $block): void {
            $block->uuid = $block->uuid ?: (string) Str::uuid();
        });

        static::saving(function (CmsBlock $block): void {
            $block->content = self::filteredContent($block);
            self::generateAnchor($block);
            self::validatePlacement($block);
        });

        static::saved(function (CmsBlock $block): void {
            self::syncMediaUsages($block);
        });

        static::deleted(function (CmsBlock $block): void {
            self::releaseMediaUsages($block);
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<CmsBlock, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Children of a container in their editor-defined order. Positions are
     * counted inside the group `owner, parent, slot`.
     *
     * @return HasMany<CmsBlock, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * The library entry a row of type `shared` points at.
     *
     * @return BelongsTo<CmsSharedBlock, $this>
     */
    public function cmsSharedBlock(): BelongsTo
    {
        return $this->belongsTo(CmsSharedBlock::class);
    }

    /**
     * The anchor of a dynamic block is mandatory, because the parameters of the
     * block are addressed by it in the query string of the page
     * (`b[raspisanie][page]=2`). An editor who left it empty gets one generated
     * out of the code of the type: `schedule`, then `schedule-2` within the
     * same owner.
     *
     * A block that is not dynamic gets nothing: an empty anchor there means
     * that the block cannot be linked to, which is a legitimate state.
     */
    private static function generateAnchor(CmsBlock $block): void
    {
        if ($block->anchor !== null && $block->anchor !== '') {
            return;
        }

        $container = Container::getInstance();

        if (! $container->bound(BlockRegistryInterface::class)) {
            return;
        }

        $registry = $container->make(BlockRegistryInterface::class);

        if (! $registry->has($block->type) || ! $registry->schemaFor($block->type)->isDynamic()) {
            return;
        }

        $block->anchor = self::freeAnchor($block);
    }

    /**
     * Uniqueness within the owner is enough: the namespace has to be unique
     * within one page, and that is exactly the scope of the column.
     */
    private static function freeAnchor(CmsBlock $block): string
    {
        $taken = self::query()
            ->where('blockable_type', $block->blockable_type)
            ->where('blockable_id', $block->blockable_id)
            ->when($block->exists, static fn ($query) => $query->whereKeyNot($block->getKey()))
            ->whereNotNull('anchor')
            ->pluck('anchor')
            ->all();

        $candidate = $block->type;
        $suffix = 1;

        while (in_array($candidate, $taken, true)) {
            $suffix++;
            $candidate = $block->type.'-'.$suffix;
        }

        return $candidate;
    }

    /**
     * Nesting is enforced on every write, not only in the admin form: drag and
     * drop changes the parent of a block without a form, and a rule that lives
     * in one place only is bypassed with a mouse.
     *
     * The validator is skipped when nothing bound it, so the model stays
     * usable without the service provider.
     */
    private static function validatePlacement(CmsBlock $block): void
    {
        $container = Container::getInstance();

        if (! $container->bound(PlacementValidator::class)) {
            return;
        }

        $validator = $container->make(PlacementValidator::class);

        $placement = ['blockable_type', 'blockable_id', 'parent_id', 'slot', 'type', 'cms_shared_block_id'];

        if (! $block->exists || $block->isDirty($placement)) {
            $validator->validate($block);
        }

        if ($block->exists && $block->isDirty('variant')) {
            $validator->validateVariantChange($block);
        }
    }

    /**
     * What this block uses out of the media library. It runs on every write and
     * not only in the admin form, for the same reason the placement check does:
     * a seeder and an import bypass a rule that lives in a form.
     *
     * A block of a type nothing registered is left alone: its content is
     * opaque without a schema, and rewriting the usages of an opaque bag would
     * release files the block still shows.
     */
    private static function syncMediaUsages(CmsBlock $block): void
    {
        $container = Container::getInstance();

        if (! $container->bound(ContentMediaUsages::class) || ! $container->bound(BlockRegistryInterface::class)) {
            return;
        }

        $registry = $container->make(BlockRegistryInterface::class);

        if (! $registry->has($block->type)) {
            return;
        }

        $container->make(ContentMediaUsages::class)->sync(
            $block,
            self::MEDIA_USAGE_FIELD,
            $block->content,
            $registry->schemaFor($block->type)->fields(),
        );
    }

    /**
     * Deleting a block frees what it used; the records and the files stay.
     */
    private static function releaseMediaUsages(CmsBlock $block): void
    {
        $container = Container::getInstance();

        if (! $container->bound(ContentMediaUsages::class)) {
            return;
        }

        $container->make(ContentMediaUsages::class)->release($block);
    }

    /**
     * Only the root keys declared by the block type survive a write. The model
     * layer stays lenient about an unregistered type: an application may seed
     * rows before registering the block class, and the renderer is the place
     * where an unknown type is skipped.
     *
     * @return array<array-key, mixed>|null
     */
    private static function filteredContent(CmsBlock $block): ?array
    {
        $content = $block->content;

        if (! is_array($content) || $content === []) {
            return $content;
        }

        $container = Container::getInstance();

        if (! $container->bound(BlockRegistryInterface::class)) {
            return $content;
        }

        $registry = $container->make(BlockRegistryInterface::class);

        if (! $registry->has($block->type)) {
            return $content;
        }

        return (new ContentFilter())->filter($content, $registry->schemaFor($block->type));
    }
}
