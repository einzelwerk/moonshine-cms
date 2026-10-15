<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Models;

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Links\LinkKind;
use Ewk\Cms\Layouts\Exceptions\InvalidMenuItemPlacementException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One entry of a menu.
 *
 * `label` is a value of the item and not a part of the link: in a menu the
 * caption is usually shorter than the title of the target. `link` stores the
 * typed link of the blocks package in its `Link::toArray()` shape — the
 * resolved address is never stored, because it goes stale on the first rename.
 * An item without a link is allowed and means a group caption of a footer
 * column.
 *
 * Two levels is the whole depth, and it is enforced here rather than in a
 * form: drag and drop moves an item without one.
 *
 * @property int $id
 * @property string $uuid
 * @property int $cms_menu_id
 * @property int|null $parent_id
 * @property int $sort_order
 * @property array<string, string> $label
 * @property array<array-key, mixed>|null $link
 * @property string|null $icon
 * @property string|null $variant
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CmsMenu $cmsMenu
 * @property-read CmsMenuItem|null $parent
 * @property-read Collection<int, CmsMenuItem> $children
 */
final class CmsMenuItem extends Model
{
    /** Root items and one level of children. */
    public const MAX_DEPTH = 2;

    /** @see CmsHeader::MORPH_ALIAS */
    public const string MORPH_ALIAS = 'cms_menu_item';

    protected $fillable = [
        'uuid',
        'cms_menu_id',
        'parent_id',
        'sort_order',
        'label',
        'link',
        'icon',
        'variant',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'label' => 'array',
        'link' => 'array',
        'cms_menu_id' => 'integer',
        'parent_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (CmsMenuItem $item): void {
            $item->uuid = $item->uuid ?: (string) Str::uuid();
        });

        static::saving(function (CmsMenuItem $item): void {
            if (! $item->exists || $item->isDirty(['cms_menu_id', 'parent_id'])) {
                self::validatePlacement($item);
            }
        });
    }

    /**
     * @return BelongsTo<CmsMenu, $this>
     */
    public function cmsMenu(): BelongsTo
    {
        return $this->belongsTo(CmsMenu::class);
    }

    /**
     * @return BelongsTo<CmsMenuItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<CmsMenuItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * The stored target as the typed link of the blocks package, or null when
     * the item carries no target at all — and also when the stored kind is no
     * longer a kind this installation knows. Telling those two apart is the
     * job of the reader: {@see \Ewk\Cms\Layouts\Chrome\ChromeDocument} reports
     * the second case as a broken item instead of a plain caption.
     */
    public function toLink(): ?Link
    {
        $stored = $this->link;

        if (! is_array($stored) || $stored === []) {
            return null;
        }

        $kind = $stored['kind'] ?? null;

        if (! is_string($kind) || ! LinkKind::tryFrom($kind) instanceof LinkKind) {
            return null;
        }

        return Link::fromArray($stored);
    }

    public function hasTarget(): bool
    {
        return is_array($this->link) && $this->link !== [];
    }

    /**
     * @param  Builder<CmsMenuItem>  $query
     * @return Builder<CmsMenuItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Everything that would produce a third level, a cycle or an item hanging
     * in a foreign menu. All of it is refused before the write: a silently
     * truncated level is learned about from an editor, not from a log.
     */
    private static function validatePlacement(CmsMenuItem $item): void
    {
        $parentId = $item->parent_id;

        if ($parentId === null) {
            return;
        }

        if ($item->exists && $parentId === $item->id) {
            throw InvalidMenuItemPlacementException::intoItself();
        }

        $parent = self::query()->find($parentId);

        if (! $parent instanceof self) {
            throw InvalidMenuItemPlacementException::unknownParent($parentId);
        }

        if ($parent->cms_menu_id !== $item->cms_menu_id) {
            throw InvalidMenuItemPlacementException::acrossMenus();
        }

        if ($parent->parent_id !== null) {
            throw InvalidMenuItemPlacementException::depthExceeded();
        }

        if (! $item->exists) {
            return;
        }

        $children = self::query()->where('parent_id', $item->id)->count();

        if ($children > 0) {
            throw InvalidMenuItemPlacementException::parentWithChildren($children);
        }
    }
}
