<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Actions;

use Ewk\Cms\Layouts\Menus\MenuSlotCatalog;
use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\Cms\Layouts\Models\CmsMenuSlotAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * The write of a menu, mounted on its resource as the save handler.
 *
 * Besides the row itself it keeps the places the menu is shown in, the rows of
 * `cms_menu_slot_assignments`. The form asks the question an editor actually has —
 * «where is this menu shown» — and a place says by itself which record holds it, so
 * nobody is asked to pick an owner for it a second time: a place of the header or the
 * footer by its kind, see {@see \Ewk\Cms\Layouts\Menus\MenuSlot::kind()}, a place the
 * installation declared by its declaration.
 *
 * Which record a new placement lands on is the answer of
 * {@see MenuSlotCatalog::ownerOf()}: for the chrome, the header or footer that
 * already fills this place and otherwise the first active record of that
 * kind; an installation with several headers arranges the rare case on the
 * form of the header itself, where the whole table is edited.
 */
final readonly class SaveMenu
{
    /**
     * The hidden mark the form of a menu sends along with its places. An empty
     * multiple select submits nothing at all, and «no places chosen» and «this
     * form never asked» would otherwise be the same request: the first has to
     * clear the rows, the second must not touch them.
     */
    public const string SUBMITTED = 'slots_submitted';

    public function __construct(
        private MenuSlotCatalog $catalog,
        private Request $request,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  the submitted form, already applied to the model
     */
    public function __invoke(CmsMenu $menu, array $data): CmsMenu
    {
        $menu->save();

        // A form that never drew the field leaves the placements alone.
        if (! $this->request->has(self::SUBMITTED)) {
            return $menu;
        }

        $submitted = $this->request->input('slots');
        $wanted = [];

        foreach (is_array($submitted) ? $submitted : [] as $code) {
            if (is_string($code) && $this->catalog->isAssignable($code)) {
                $wanted[] = $code;
            }
        }

        $this->drop($menu, $wanted);
        $this->add($menu, $wanted);

        return $menu;
    }

    /**
     * Only the places this form offers are its to clear. A place added through
     * `cms-layouts.menu_slots` is filled on the form of the header or the
     * footer and never shown here, so a row in it is not the editor unchecking
     * anything and survives the save.
     *
     * @param  list<string>  $wanted
     */
    private function drop(CmsMenu $menu, array $wanted): void
    {
        $offered = array_values(array_filter(
            $this->catalog->codes(),
            fn (string $code): bool => $this->catalog->isAssignable($code),
        ));

        $query = CmsMenuSlotAssignment::query()
            ->where('cms_menu_id', $menu->getKey())
            ->whereIn('slot', $offered);

        if ($wanted !== []) {
            $query->whereNotIn('slot', $wanted);
        }

        $query->delete();
    }

    /**
     * @param  list<string>  $wanted
     */
    private function add(CmsMenu $menu, array $wanted): void
    {
        foreach ($wanted as $slot) {
            $taken = CmsMenuSlotAssignment::query()
                ->where('cms_menu_id', $menu->getKey())
                ->where('slot', $slot)
                ->exists();

            if ($taken) {
                continue;
            }

            $owner = $this->catalog->ownerOf($slot);

            // Nothing to hang the menu on: an installation without a header or
            // a footer, or without the record a declared place names, has no
            // such place yet, and the form says so.
            if (! $owner instanceof Model) {
                continue;
            }

            CmsMenuSlotAssignment::query()->create([
                'owner_type' => $owner->getMorphClass(),
                'owner_id' => $owner->getKey(),
                'slot' => $slot,
                'cms_menu_id' => $menu->getKey(),
                'sort_order' => CmsMenuSlotAssignment::query()
                    ->where('owner_type', $owner->getMorphClass())
                    ->where('owner_id', $owner->getKey())
                    ->where('slot', $slot)
                    ->count(),
                'is_enabled' => true,
            ]);
        }
    }
}
