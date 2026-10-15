<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A named list of links. A menu is not attached to a page or to a layout: it is
 * put into a slot of a header or of a footer, see {@see CmsMenuSlotAssignment}.
 *
 * `source` names where the items come from — the manual source of the core,
 * or one an application registers, categories being the first case.
 *
 * @property int $id
 * @property string $uuid
 * @property string $code
 * @property array<string, string> $name
 * @property string $source
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, CmsMenuItem> $items
 * @property-read Collection<int, CmsMenuItem> $activeItems
 */
final class CmsMenu extends Model
{
    public const SOURCE_MANUAL = 'manual';

    /** @see CmsHeader::MORPH_ALIAS */
    public const string MORPH_ALIAS = 'cms_menu';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'source',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'name' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (CmsMenu $menu): void {
            $menu->uuid = $menu->uuid ?: (string) Str::uuid();
            $menu->source = $menu->source ?: self::SOURCE_MANUAL;
        });
    }

    /**
     * Every item of the menu, both levels, in editor order.
     *
     * @return HasMany<CmsMenuItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CmsMenuItem::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * The items a visitor sees. Visibility of an item is its activity in the
     * first version, and the filter belongs here rather than to the frontend:
     * an item hidden by styles is still in the source of the page.
     *
     * @return HasMany<CmsMenuItem, $this>
     */
    public function activeItems(): HasMany
    {
        return $this->items()->where('is_active', true);
    }

    /**
     * @param  Builder<CmsMenu>  $query
     * @return Builder<CmsMenu>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
