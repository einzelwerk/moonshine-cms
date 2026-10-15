<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Concerns;

use Ewk\Cms\Blocks\Actions\MoveBlock;
use Ewk\Cms\Blocks\Actions\PublishOwner;
use Ewk\Cms\Blocks\Exceptions\BlockCycleException;
use Ewk\Cms\Blocks\Exceptions\InvalidBlockPlacementException;
use Ewk\Cms\Blocks\Exceptions\PublicationRejectedException;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Blocks\Support\RequiredBlockGuard;
use Ewk\MoonShineCms\Editor\BlockLibrary;
use Ewk\MoonShineCms\Editor\InsertBlock;
use Ewk\MoonShineCms\Editor\LibraryCategory;
use Ewk\MoonShineCms\Editor\SaveBlock;
use Ewk\MoonShineCms\Exceptions\BlockVersionConflictException;
use Ewk\MoonShineCms\MoonShine\Components\BlockEditor;
use Ewk\MoonShineCms\MoonShine\Components\EditorSwitcher;
use Ewk\MoonShineCms\MoonShine\Pages\BlockEditorPage;
use Ewk\MoonShineCms\Support\BlockVersion;
use Ewk\MoonShineCms\Support\ModelKey;
use Ewk\MoonShineCms\Support\WriteRequest;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\JsonResponse as CrudJsonResponse;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\Support\Enums\ToastType;
use MoonShine\UI\Enums\HtmlMode;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The HTTP commands of the block editor, mounted on the MoonShine resource that
 * owns the blocks. Every command is its own endpoint: a card saves alone, a
 * move is checked on the server, and publication reports what stands in its
 * way.
 *
 * The request is never typed as a MoonShine request in these signatures: the
 * container would build an empty one without the payload.
 *
 * @phpstan-require-extends \MoonShine\Laravel\Resources\ModelResource
 * @phpstan-require-implements \Ewk\MoonShineCms\Contracts\EditsBlocksContract
 */
// @phpstan-ignore trait.unused (package export: a host resource applies it to its own model)
trait InteractsWithBlockEditor
{
    /**
     * The address of the block editor of one item: the page of this resource
     * that extends {@see BlockEditorPage}.
     */
    public function blockEditorPageUrl(int|string $key): string
    {
        foreach ($this->getPages() as $page) {
            if ($page instanceof BlockEditorPage) {
                return $this->getPageUrl($page, ['resourceItem' => $key]);
            }
        }

        throw new RuntimeException('This resource registers no block editor page.');
    }

    /**
     * «Settings | Blocks»: the two views of one record, rendered the same on
     * the form of the item and on the editor, see {@see EditorSwitcher}.
     */
    public function blockEditorSwitcher(bool $editorActive): ComponentContract
    {
        $key = ModelKey::of($this->blockEditorOwner());

        return EditorSwitcher::between(
            $this->getFormPageUrl($key),
            $this->blockEditorPageUrl($key),
            $editorActive,
        );
    }

    /**
     * Saving one card. Nothing but the submitted block is touched, and the
     * version of the row decides whether the write happens at all.
     *
     * The answer redraws the head of that one card and nothing else: the
     * caption and the badges follow the content, while the form under them
     * holds what the editor is still typing, and a rich text editor inside it
     * does not survive being replaced. The new version marker travels the same
     * way, into the hidden input of the card.
     */
    #[AsyncMethod]
    public function saveCmsBlock(Request $request, SaveBlock $action): CrudJsonResponse|JsonResponse
    {
        $block = $this->cmsBlockOfRequest($request);

        if (! $block instanceof CmsBlock) {
            return $this->cmsProblem('unknown_block', Response::HTTP_NOT_FOUND);
        }

        try {
            $saved = $action->handle($block, $request->all());
        } catch (BlockVersionConflictException $conflict) {
            return new JsonResponse(
                ['message' => $conflict->getMessage()],
                Response::HTTP_CONFLICT,
            );
        }

        $key = $saved->id;
        $version = BlockVersion::of($saved);

        $answer = CrudJsonResponse::make()
            ->toast((string) __('moonshine-cms::editor.saved'), ToastType::SUCCESS)
            ->events([AlpineJs::event('cms-block-saved', null, ['block' => (string) $key])])
            ->fieldsValues(['form[data-cms-form="'.$key.'"] input[name="version"]' => $version])
            ->merge(['block' => $key, 'version' => $version]);

        $head = BlockEditor::cardHead(
            $this->blockEditorOwner(),
            $key,
            $this->cmsEndpoint('deleteCmsBlock'),
            AlpineJs::event(JsEvent::FRAGMENT_UPDATED, BlockEditor::FRAGMENT),
        );

        return $head === null
            ? $answer
            : $answer->htmlData($head, '[data-cms-card-head="'.$key.'"]', HtmlMode::OUTER_HTML);
    }

    /**
     * Reordering and moving into a slot. Both go through the one action that
     * validates the new parent, the slot and the depth: a rule implemented in
     * the interface only is bypassed with a direct request.
     *
     * The payload is the one the sortable of the panel speaks: `id`, the
     * `parent` of the list the card landed in, its `slot` and the `index` it
     * took there.
     */
    #[AsyncMethod]
    public function moveCmsBlock(Request $request, MoveBlock $action): JsonResponse
    {
        $refused = WriteRequest::refuse($request);

        if ($refused instanceof JsonResponse) {
            return $refused;
        }

        $block = $this->cmsBlockOfRequest($request);

        if (! $block instanceof CmsBlock) {
            return $this->cmsProblem('unknown_block', Response::HTTP_NOT_FOUND);
        }

        $parent = $request->input('parent');
        $slot = $request->input('slot');
        $position = $request->input('index', $request->input('position'));

        try {
            $moved = $action->handle(
                $block,
                is_numeric($parent) ? (int) $parent : null,
                is_string($slot) && $slot !== '' ? $slot : null,
                is_numeric($position) ? (int) $position : null,
            );
        } catch (InvalidBlockPlacementException|BlockCycleException $rejected) {
            return new JsonResponse(
                ['message' => $rejected->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return new JsonResponse([
            'message' => (string) __('moonshine-cms::editor.moved'),
            'block' => $moved->getKey(),
            'position' => $moved->sort_order,
        ]);
    }

    /**
     * Deleting one block. A container that still holds children is refused by
     * the foreign key of the storage; the editor is told so instead of the
     * error being hidden.
     *
     * The last instance of a block code the owner requires is refused here and
     * not only hidden in the card: deletion has its own request, and a hidden
     * button is a hint.
     */
    #[AsyncMethod]
    public function deleteCmsBlock(Request $request, MoveBlock $move, RequiredBlockGuard $required): JsonResponse
    {
        $refused = WriteRequest::refuse($request);

        if ($refused instanceof JsonResponse) {
            return $refused;
        }

        $block = $this->cmsBlockOfRequest($request);

        if (! $block instanceof CmsBlock) {
            return $this->cmsProblem('unknown_block', Response::HTTP_NOT_FOUND);
        }

        if ($required->protectsRemoval($block, $this->blockEditorOwner())) {
            return $this->cmsProblem('required_block', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $parentId = $block->parent_id;
        $slot = $block->slot;

        try {
            $block->delete();
        } catch (QueryException $refused) { // @phpstan-ignore catch.neverThrown (the foreign key of a child refuses the delete)
            report($refused);

            return $this->cmsProblem('container_not_empty', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->cmsCompactGroup($move, $block, $parentId, $slot);

        return new JsonResponse(['message' => (string) __('moonshine-cms::editor.deleted')]);
    }

    /**
     * Inserting from the library at an exact position, including into a named
     * slot of a container.
     */
    #[AsyncMethod]
    public function insertCmsBlock(Request $request, InsertBlock $action): JsonResponse
    {
        $refused = WriteRequest::refuse($request);

        if ($refused instanceof JsonResponse) {
            return $refused;
        }

        $parent = $request->input('parent');
        $slot = $request->input('slot');
        $position = $request->input('position');

        try {
            $block = $this->cmsInsert(
                $action,
                $request,
                is_numeric($parent) ? (int) $parent : null,
                is_string($slot) && $slot !== '' ? $slot : null,
                is_numeric($position) ? (int) $position : null,
            );
        } catch (InvalidBlockPlacementException|BlockCycleException $rejected) {
            return new JsonResponse(
                ['message' => $rejected->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if (! $block instanceof CmsBlock) {
            return $this->cmsProblem('unknown_type', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'message' => (string) __('moonshine-cms::editor.inserted'),
            'block' => $block->getKey(),
        ]);
    }

    /**
     * The library the modal shows: every category, or the narrowed list a
     * named slot of a container accepts. It only reads, so a GET is what the
     * modal of the panel fetches it with; the insertion itself is a POST.
     *
     * The answer is the markup of the tiles and not a payload the browser
     * assembles: the modal of MoonShine loads HTML, and the search inside it
     * is a filter over what is already drawn.
     */
    #[AsyncMethod]
    public function cmsBlockLibrary(Request $request, BlockLibrary $library): string
    {
        $search = $request->input('search');
        $search = is_string($search) ? $search : '';

        $parent = $request->input('parent');
        $slot = $request->input('slot');
        $position = $request->input('position');
        $categories = null;

        if (is_numeric($parent) && is_string($slot) && $slot !== '') {
            $container = $this->cmsOwnedBlock((int) $parent);

            $categories = $container instanceof CmsBlock
                ? $library->categoriesForSlot($container, $slot, $search)
                : [];
        }

        $categories ??= $library->categories($search);

        // A string answer is handed to the browser as it is; anything else
        // would be wrapped into the json envelope of the panel.
        /** @var view-string $view */
        $view = 'moonshine-cms::components.block-editor-library';

        return $this->getCore()->getContainer(ViewFactory::class)->make($view, [
            'categories' => $this->cmsNarrowLibrary($categories, $request),
            'parent' => is_numeric($parent) ? (string) (int) $parent : '',
            'slotName' => is_string($slot) ? $slot : '',
            'position' => is_numeric($position) ? (int) $position : 0,
        ])->render();
    }

    /**
     * Publishing the owner. A rejected publication writes nothing and returns
     * the whole list of what is in the way.
     */
    #[AsyncMethod]
    public function publishCmsOwner(Request $request, PublishOwner $action): JsonResponse
    {
        $refused = WriteRequest::refuse($request);

        if ($refused instanceof JsonResponse) {
            return $refused;
        }

        try {
            $version = $action->handle($this->blockEditorOwner());
        } catch (PublicationRejectedException $rejected) {
            return new JsonResponse([
                'message' => (string) __('moonshine-cms::editor.publication_rejected'),
                'problems' => $rejected->problems,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse([
            'message' => (string) __('moonshine-cms::editor.published'),
            'number' => $version->number,
        ]);
    }

    /**
     * What this owner narrows the library down to. The slot of a container has
     * already had its say; an owner that limits the root level of its own
     * blocks says so here.
     *
     * The interface is a hint either way: the insertion itself is checked
     * again on the server.
     *
     * @param  list<LibraryCategory>  $categories
     * @return list<LibraryCategory>
     */
    protected function cmsNarrowLibrary(array $categories, Request $request): array
    {
        return $categories;
    }

    /**
     * The owner of the blocks: the item of this resource. A resource whose
     * blocks belong to something else overrides this.
     */
    public function blockEditorOwner(): Model
    {
        $item = $this->getItem();

        if (! $item instanceof Model) {
            throw new RuntimeException('The block editor needs a persisted owner.');
        }

        return $item;
    }

    private function cmsInsert(
        InsertBlock $action,
        Request $request,
        ?int $parentId,
        ?string $slot,
        ?int $position,
    ): ?CmsBlock {
        $owner = $this->blockEditorOwner();
        $sharedBlockId = $request->input('shared_block');

        if (is_numeric($sharedBlockId)) {
            $sharedBlock = CmsSharedBlock::query()->find((int) $sharedBlockId);

            return $sharedBlock instanceof CmsSharedBlock
                ? $action->handleShared($owner, $sharedBlock, $parentId, $slot, $position)
                : null;
        }

        $code = $request->input('code');
        $variant = $request->input('variant');

        if (! is_string($code) || $code === '') {
            return null;
        }

        return $action->handle(
            $owner,
            $code,
            is_string($variant) && $variant !== '' ? $variant : null,
            $parentId,
            $slot,
            $position,
        );
    }

    /**
     * Positions are dense inside the group `owner, parent, slot`. A delete
     * leaves a hole, and moving the first survivor to the top renumbers the
     * whole group through the action that owns the invariant.
     */
    private function cmsCompactGroup(MoveBlock $move, CmsBlock $deleted, ?int $parentId, ?string $slot): void
    {
        $survivor = $this->cmsGroupQuery($deleted, $parentId, $slot)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($survivor instanceof CmsBlock) {
            $move->handle($survivor, $parentId, $slot, 0);
        }
    }

    /**
     * @return Builder<CmsBlock>
     */
    private function cmsGroupQuery(CmsBlock $block, ?int $parentId, ?string $slot): Builder
    {
        $query = CmsBlock::query()
            ->where('blockable_type', $block->blockable_type)
            ->where('blockable_id', $block->blockable_id);

        $parentId === null
            ? $query->whereNull('parent_id')
            : $query->where('parent_id', $parentId);

        $slot === null
            ? $query->whereNull('slot')
            : $query->where('slot', $slot);

        return $query;
    }

    /**
     * The block named by the request, but only inside the owner of this
     * resource: a crafted identifier must not reach another page.
     */
    private function cmsBlockOfRequest(Request $request): ?CmsBlock
    {
        // `block` is what a card of the editor sends; `id` is what the
        // sortable of the panel names the dragged row.
        $id = $request->input('block', $request->input('id'));

        return is_numeric($id) ? $this->cmsOwnedBlock((int) $id) : null;
    }

    /**
     * The address of one command of this editor, for an answer that has to
     * name a button it draws again.
     */
    private function cmsEndpoint(string $method): string
    {
        $request = $this->getCore()->getCrudRequest();

        return $this->getCore()->getRouter()->getEndpoints()->method(
            $method,
            params: ['resourceItem' => $this->blockEditorOwner()->getKey()],
            page: $request->getPage(),
            resource: $request->hasResource() ? $request->getResource() : null,
        );
    }

    private function cmsOwnedBlock(int $id): ?CmsBlock
    {
        $owner = $this->blockEditorOwner();

        return CmsBlock::query()
            ->whereKey($id)
            ->where('blockable_type', $owner->getMorphClass())
            ->where('blockable_id', $owner->getKey())
            ->first();
    }

    private function cmsProblem(string $key, int $status): JsonResponse
    {
        return new JsonResponse(
            ['message' => (string) __('moonshine-cms::editor.'.$key)],
            $status,
        );
    }
}
