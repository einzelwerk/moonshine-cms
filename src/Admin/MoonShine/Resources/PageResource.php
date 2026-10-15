<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Pages\Exceptions\InvalidPagePlacementException;
use Ewk\Cms\Pages\Exceptions\PathCollisionException;
use Ewk\Cms\Pages\Exceptions\ReservedSegmentException;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Services\AssembleTemplate;
use Ewk\Cms\Pages\Services\PageTree;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\MoonShineCms\Actions\SavePage;
use Ewk\MoonShineCms\Concerns\InteractsWithBlockEditor;
use Ewk\MoonShineCms\Concerns\InteractsWithSeoFields;
use Ewk\MoonShineCms\Contracts\EditsBlocksContract;
use Ewk\MoonShineCms\Editor\InsertBlock;
use Ewk\MoonShineCms\Editor\LibraryCategory;
use Ewk\MoonShineCms\Editor\LibraryEntry;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\PageBlocksPage;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\PageFormPage;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\PageIndexPage;
use Ewk\MoonShineCms\Support\WriteRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\FieldContract;
use MoonShine\Core\Exceptions\ResourceException;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\UI\Fields\Field;
use Symfony\Component\HttpFoundation\Response;

/**
 * The pages of the tree: the free pages an editor creates and deletes, plus the
 * singletons, which are marked and protected.
 *
 * A template is not a row an editor creates, so it is not a node of the tree
 * either: the tiles of the template types sit under the tree, see
 * {@see \Ewk\MoonShineCms\MoonShine\Components\PageTreeView}, and the one
 * command they carry lives on this resource.
 *
 * Not final: an installation extends it to add what belongs to the
 * application — a tab of its own on the form, a model with more traits — and
 * names the subclass in `moonshine-cms.page_resource`. PHP attributes are not
 * inherited, so the subclass repeats the menu attributes (`Group`, `Icon`,
 * `Order`) it wants to keep; the save handler is wired in {@see save()} for
 * the same reason and needs nothing from the subclass.
 *
 * @extends ModelResource<CmsPage, PageIndexPage, PageFormPage>
 */
#[Group('Content', 'document-text')]
#[Icon('document-duplicate')]
#[Order(10)]
class PageResource extends ModelResource implements EditsBlocksContract
{
    use InteractsWithBlockEditor {
        insertCmsBlock as private packageInsertCmsBlock;
    }
    use InteractsWithSeoFields;

    protected string $model = CmsPage::class;

    protected string $column = 'slug';

    protected string $sortColumn = 'path';

    protected SortDirection $sortDirection = SortDirection::ASC;

    public function __construct(CoreContract $core)
    {
        // The model of the installation, see cms-pages.model: the rows the
        // panel edits carry the traits of the application.
        $this->model = PageModel::class();

        parent::__construct($core);
    }

    public function getTitle(): string
    {
        return (string) __('moonshine-cms::pages.title');
    }

    protected function pages(): array
    {
        return [
            PageIndexPage::class,
            PageFormPage::class,
            PageBlocksPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'slug', 'path'];
    }

    /**
     * The write goes through {@see SavePage}: the address of a page is
     * derived by the tree, never stored by a bare `save()`. The handler
     * replaces the whole save of MoonShine, the way a `SaveHandler` attribute
     * would — done here in code, so that a subclass of an installation keeps
     * it without repeating the attribute.
     *
     * A refusal of the tree is reported to the editor on the field he wrote,
     * exactly as {@see PageResource::movePage()} reports it: a path colliding
     * with a route of the frontend, with a sibling or with a template is a
     * wrong value in the form and never a failure of the panel.
     *
     * @param  DataWrapperContract<CmsPage>  $item
     *
     * @throws ValidationException when the tree refuses the address
     */
    public function save(DataWrapperContract $item, ?FieldsContract $fields = null): DataWrapperContract
    {
        $fields ??= $this->getFormFields()->onlyFields(withApplyWrappers: true);

        $fields->fill($item->toArray(), $item);

        $page = $item->getOriginal();

        $data = Field::silentApply(function () use ($item, $fields, $page): array {
            $fields->each(static fn (FieldContract $field): mixed => $field->beforeApply($page));
            $fields->each(fn (FieldContract $field): mixed => $field->apply($this->fieldApply($field), $page));
            $fields->each(static fn (FieldContract $field): mixed => $field->afterApply($page));

            return $item->toArray();
        });

        try {
            $saved = $this->getCore()->getContainer(SavePage::class)($page, $data);
        } catch (InvalidPagePlacementException|PathCollisionException|ReservedSegmentException $refused) {
            throw ValidationException::withMessages(['slug' => $refused->getMessage()]);
        }

        // The metadata tab, after the row has a key: `cms_metadata` points at
        // the page by the polymorphic pair, and a page created by this very
        // request has no key until the tree has placed it, see
        // Ewk\MoonShineCms\Actions\SaveSeoMetadata.
        $this->saveSeo($saved, request()->all());

        $this->setItem($saved);

        return $this->getCastedData() ?? $item;
    }

    /**
     * The handler above replaces the save of MoonShine — including the flag
     * that decides where the editor lands afterwards. The model knows it.
     */
    public function isRecentlyCreated(): bool
    {
        $item = $this->getItem();

        return $item instanceof CmsPage ? $item->wasRecentlyCreated : parent::isRecentlyCreated();
    }

    /**
     * A page whose address comes from its type declaration is not the editor's
     * to delete: a singleton is created by the installation and a template
     * belongs to the set of types, which is code.
     *
     * The check sits in the one method both the row action and the mass
     * deletion go through.
     *
     * @param  DataWrapperContract<CmsPage>  $item
     *
     * @throws ResourceException
     */
    public function delete(DataWrapperContract $item, ?FieldsContract $fields = null): bool
    {
        $page = $item->getOriginal();
        $registry = $this->types();

        if ($registry->has($page->type) && $registry->get($page->type)->hasFixedPath()) {
            throw new ResourceException(
                (string) __('moonshine-cms::pages.fixed_path_not_deletable', [
                    'type' => $registry->get($page->type)->title(),
                ]),
            );
        }

        return parent::delete($item, $fields);
    }

    /**
     * A drop in the tree: the node goes under another parent, or to another
     * place among its siblings. The checks — not into itself or its own
     * subtree, not under a template, the depth, the reserved segments, the
     * collision of paths — belong to the tree of the pages package and are
     * reported back to the editor instead of being repeated here.
     */
    #[AsyncMethod]
    public function movePage(Request $request, PageTree $tree): JsonResponse
    {
        $refused = WriteRequest::refuse($request);

        if ($refused instanceof JsonResponse) {
            return $refused;
        }

        $page = PageModel::query()->find($request->input('page'));

        if (! $page instanceof CmsPage) {
            return new JsonResponse(
                ['message' => (string) __('moonshine-cms::pages.unknown_page')],
                Response::HTTP_NOT_FOUND,
            );
        }

        $parentKey = $request->input('parent');
        $parent = is_numeric($parentKey) ? PageModel::query()->find((int) $parentKey) : null;

        if (is_numeric($parentKey) && ! $parent instanceof CmsPage) {
            return new JsonResponse(
                ['message' => (string) __('moonshine-cms::pages.unknown_page')],
                Response::HTTP_NOT_FOUND,
            );
        }

        $position = $request->input('position');

        try {
            $tree->move($page, $parent, is_numeric($position) ? (int) $position : null);
        } catch (InvalidPagePlacementException|PathCollisionException|ReservedSegmentException $refused) {
            return new JsonResponse(['message' => $refused->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'message' => (string) __('moonshine-cms::pages.moved'),
            'page' => $page->getKey(),
            'path' => $page->path,
        ]);
    }

    /**
     * Build the template of a type from the starter set of its declaration.
     * The tile under the tree offers this only while the type has no row at
     * all, and the cardinality of the tree refuses a second one anyway.
     *
     * It writes a row, so it answers a POST and nothing else, see
     * {@see WriteRequest}: on a GET the address assembles a template for
     * anyone whose browser is made to load it. The answer leads into the block
     * editor of the template, assembled just now or already there.
     */
    #[AsyncMethod]
    public function assembleTemplate(Request $request, AssembleTemplate $assemble): JsonResponse
    {
        $refused = WriteRequest::refuse($request);

        if ($refused instanceof JsonResponse) {
            return $refused;
        }

        $key = $request->input('type');
        $registry = $this->types();

        if (! is_string($key) || ! $registry->has($key) || ! $registry->get($key)->isTemplate()) {
            return new JsonResponse(
                ['message' => (string) __('moonshine-cms::pages.unknown_template_type')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $existing = $assemble->existing($key);

        if ($existing instanceof CmsPage) {
            return new JsonResponse(['redirect' => $this->blockEditorPageUrl($existing->id)]);
        }

        $page = $assemble($key);

        return new JsonResponse([
            'message' => (string) __('moonshine-cms::pages.template_assembled'),
            'redirect' => $this->blockEditorPageUrl($page->id),
        ]);
    }

    /**
     * Which block types this page offers. Availability at the root level of a
     * page is declared by the block per page type and checked by the owner of
     * the blocks, so a template block never shows up on an ordinary page.
     *
     * A slot of a container narrows the list by its own declarations; the page
     * type narrows the root level only.
     *
     * @param  list<LibraryCategory>  $categories
     * @return list<LibraryCategory>
     */
    protected function cmsNarrowLibrary(array $categories, Request $request): array
    {
        if (is_numeric($request->input('parent'))) {
            return $categories;
        }

        return $this->allowedCategories($categories);
    }

    /**
     * The same rule on the server: a hidden card in the modal is a hint, a
     * crafted request is the real check.
     */
    #[AsyncMethod]
    public function insertCmsBlock(Request $request, InsertBlock $action): JsonResponse
    {
        $code = $request->input('code');

        if (is_string($code) && $code !== '' && ! is_numeric($request->input('parent')) && ! $this->allowsAtRoot($code)) {
            return new JsonResponse(
                ['message' => (string) __('moonshine-cms::pages.block_not_allowed')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->packageInsertCmsBlock($request, $action);
    }

    /**
     * @param  list<LibraryCategory>  $categories
     * @return list<LibraryCategory>
     */
    private function allowedCategories(array $categories): array
    {
        $allowed = [];

        foreach ($categories as $category) {
            $entries = array_values(array_filter(
                $category->entries,
                // An entry of the shared library carries the type of its
                // original and is offered wherever that type is allowed.
                fn (LibraryEntry $entry): bool => $this->allowsAtRoot($entry->code),
            ));

            if ($entries !== []) {
                $allowed[] = new LibraryCategory(
                    code: $category->code,
                    title: $category->title,
                    entries: $entries,
                    shared: $category->shared,
                );
            }
        }

        return $allowed;
    }

    private function allowsAtRoot(string $code): bool
    {
        $registry = $this->getCore()->getContainer(BlockRegistryInterface::class);

        if (! $registry->has($code)) {
            return false;
        }

        // A declaration is either the wildcard, a list of page type codes, or
        // the empty list of a block that is allowed nowhere at the root.
        $root = $registry->schemaFor($code)->placementRoot();

        if ($root === '*') {
            return true;
        }

        if (! is_array($root)) {
            return false;
        }

        if (in_array('*', $root, true)) {
            return true;
        }

        $page = $this->getItem();

        return $page instanceof CmsPage && in_array($page->type, $root, true);
    }

    private function types(): PageTypeRegistry
    {
        return $this->getCore()->getContainer(PageTypeRegistry::class);
    }
}
