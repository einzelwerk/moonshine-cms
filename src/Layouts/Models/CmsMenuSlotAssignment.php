<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Models;

use Ewk\Cms\Layouts\Exceptions\UnknownMenuSlotException;
use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A menu put into a slot of a header or of a footer — the row of
 * `cms_menu_slot_assignments`. Slots belong to the header and the footer and
 * not to the layout, because the place in the markup is theirs. A place an
 * installation declared together with its owner ({@see
 * \Ewk\Cms\Layouts\Menus\MenuSlotDefinition}) hangs on that record instead, the
 * footer of a letter for one, so the owner is any model and not only a header
 * or a footer.
 *
 * The slot code is checked against the catalog on write: the dictionary is
 * closed, so an unknown code is a mistake now instead of an empty area in the
 * frontend later. The check is skipped when nothing bound the catalog, which
 * keeps the model usable without the service provider.
 *
 * @property int $id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $slot
 * @property int $cms_menu_id
 * @property int $sort_order
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $owner
 * @property-read CmsMenu $cmsMenu
 */
final class CmsMenuSlotAssignment extends Model
{
    /** @see CmsHeader::MORPH_ALIAS */
    public const string MORPH_ALIAS = 'cms_menu_slot_assignment';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'slot',
        'cms_menu_id',
        'sort_order',
        'is_enabled',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'owner_id' => 'integer',
        'cms_menu_id' => 'integer',
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (CmsMenuSlotAssignment $assignment): void {
            $container = Container::getInstance();

            if (! $container->bound(MenuSlotCatalog::class)) {
                return;
            }

            $catalog = $container->make(MenuSlotCatalog::class);

            if (! $catalog->has($assignment->slot)) {
                throw UnknownMenuSlotException::forCode($assignment->slot, $catalog->codes());
            }
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<CmsMenu, $this>
     */
    public function cmsMenu(): BelongsTo
    {
        return $this->belongsTo(CmsMenu::class);
    }
}
