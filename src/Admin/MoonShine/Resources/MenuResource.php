<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources;

use Ewk\Cms\Layouts\Exceptions\InvalidMenuItemPlacementException;
use Ewk\Cms\Layouts\Menus\MenuSourceRegistry;
use Ewk\Cms\Layouts\Models\CmsMenu;
use Ewk\Cms\Layouts\Models\CmsMenuItem;
use Ewk\MoonShineCms\Actions\SaveMenu;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\MenuFormPage;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\MenuIndexPage;
use Ewk\MoonShineCms\Support\WriteRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MoonShine\Crud\Attributes\SaveHandler;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Attributes\Icon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Named lists of links. A menu is attached neither to a page nor to a layout:
 * it is put into a slot of a header or of a footer.
 *
 * `source` says where the items come from. The package stores it as a
 * description and never as executable code — the document always delivers
 * what lies in `cms_menu_items` — so a menu fed by a dictionary of the
 * installation is filled by the action below and not by a query at read
 * time. Which dictionaries exist is declared in the {@see MenuSourceRegistry}.
 *
 * @extends ModelResource<CmsMenu, MenuIndexPage, MenuFormPage>
 */
#[Group('Content', 'document-text')]
#[Icon('list-bullet')]
#[Order(15)]
#[SaveHandler(SaveMenu::class)]
class MenuResource extends ModelResource
{
    protected bool $usePagination = false;

    protected string $model = CmsMenu::class;

    protected string $column = 'code';

    public function getTitle(): string
    {
        return (string) __('moonshine-cms::menus.title');
    }

    protected function pages(): array
    {
        return [
            MenuIndexPage::class,
            MenuFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'code'];
    }

    /**
     * Fill the menu from its source: the button offered only on a menu whose
     * source is a registered dictionary.
     *
     * It deletes the items the menu holds and writes the ones the dictionary
     * hands out, so it answers a POST and nothing else, see
     * {@see WriteRequest}: on a GET the address rewrites the menu of anyone
     * whose browser is made to load it.
     */
    #[AsyncMethod]
    public function fillFromSource(Request $request): JsonResponse
    {
        $refused = WriteRequest::refuse($request);

        if ($refused instanceof JsonResponse) {
            return $refused;
        }

        $menu = CmsMenu::query()->find($request->input('resourceItem'));
        $sources = $this->getCore()->getContainer(MenuSourceRegistry::class);

        if (! $menu instanceof CmsMenu || ! $sources->has($menu->source)) {
            return new JsonResponse(
                ['message' => (string) __('moonshine-cms::menus.fill_wrong_source')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $count = $sources->get($menu->source)->fill($menu);

        return new JsonResponse([
            'message' => (string) ($count === 0
                ? __('moonshine-cms::menus.fill_nothing')
                : __('moonshine-cms::menus.fill_done', ['count' => $count])),
            'count' => $count,
            'redirect' => $this->getFormPageUrl($menu->id),
        ]);
    }

    /**
     * A drop in the tree of the items: the item goes under another parent, or
     * to another place among its siblings.
     *
     * Two levels is the whole depth, and the rule lives in the model of the
     * layouts package — a third level is refused there and reported to the
     * editor here, never checked twice. Position keeps the group of siblings
     * dense, so a reorder cannot leave holes for the next drop to fall into.
     */
    #[AsyncMethod]
    public function moveMenuItem(Request $request): JsonResponse
    {
        $refused = WriteRequest::refuse($request);

        if ($refused instanceof JsonResponse) {
            return $refused;
        }

        $item = $this->menuItemOfRequest($request, 'item');

        if (! $item instanceof CmsMenuItem) {
            return $this->menuProblem('item_unknown', Response::HTTP_NOT_FOUND);
        }

        $parentKey = $request->input('parent');
        $parent = is_numeric($parentKey) ? $this->menuItemOfRequest($request, 'parent') : null;

        if (is_numeric($parentKey) && ! $parent instanceof CmsMenuItem) {
            return $this->menuProblem('item_unknown', Response::HTTP_NOT_FOUND);
        }

        $position = $request->input('position');
        $position = is_numeric($position) ? (int) $position : null;
        $sourceParentId = $item->parent_id;

        try {
            DB::transaction(function () use ($item, $parent, $position, $sourceParentId): void {
                $item->parent_id = $parent?->id;
                $item->save();

                $this->sequenceMenuItems($item, $position);

                // The group the item came from keeps a hole otherwise, and
                // the next drop into it falls between two equal positions.
                if ($sourceParentId !== $item->parent_id) {
                    $this->compactMenuItems($item, $sourceParentId);
                }
            });
        } catch (InvalidMenuItemPlacementException $refused) {
            return new JsonResponse(['message' => $refused->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'message' => (string) __('moonshine-cms::menus.item_moved'),
            'item' => $item->getKey(),
            'parent' => $item->parent_id,
            'position' => $item->sort_order,
        ]);
    }

    /**
     * Positions are dense inside the group «menu, parent»: the siblings are
     * renumbered from zero with the moved item put where it was dropped.
     */
    private function sequenceMenuItems(CmsMenuItem $item, ?int $position): void
    {
        $siblings = $this->menuSiblings($item, $item->parent_id)
            ->whereKeyNot($item->getKey())
            ->get()
            ->all();

        $position = $position === null ? count($siblings) : max(0, min($position, count($siblings)));

        array_splice($siblings, $position, 0, [$item]);

        $this->renumber($siblings);
    }

    /**
     * The group the item left, closed up: positions are dense inside a group
     * and a move out of one is a hole exactly as a delete is.
     */
    private function compactMenuItems(CmsMenuItem $item, ?int $parentId): void
    {
        $this->renumber($this->menuSiblings($item, $parentId)->get()->all());
    }

    /**
     * @param  array<int, CmsMenuItem>  $items
     */
    private function renumber(array $items): void
    {
        foreach ($items as $order => $item) {
            if ($item->sort_order !== $order) {
                $item->sort_order = $order;
                $item->save();
            }
        }
    }

    /**
     * The group «menu, parent» in its stored order.
     *
     * @return Builder<CmsMenuItem>
     */
    private function menuSiblings(CmsMenuItem $item, ?int $parentId): Builder
    {
        $query = CmsMenuItem::query()->where('cms_menu_id', $item->cms_menu_id);

        $parentId === null
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $parentId);

        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * An item of the request, but only inside the menu the address names: a
     * crafted identifier must not renumber the items of another menu, the way
     * a block command never reaches another owner.
     */
    private function menuItemOfRequest(Request $request, string $key): ?CmsMenuItem
    {
        $menu = $this->getItem();
        $id = $request->input($key);

        if (! $menu instanceof CmsMenu || ! is_numeric($id)) {
            return null;
        }

        return CmsMenuItem::query()
            ->whereKey((int) $id)
            ->where('cms_menu_id', $menu->getKey())
            ->first();
    }

    private function menuProblem(string $key, int $status): JsonResponse
    {
        return new JsonResponse(['message' => (string) __('moonshine-cms::menus.'.$key)], $status);
    }
}
