<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources;

use Ewk\Cms\Blocks\Actions\MoveBlock;
use Ewk\Cms\Blocks\Actions\UnlinkSharedBlock;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\MoonShineCms\Concerns\InteractsWithBlockEditor;
use Ewk\MoonShineCms\Contracts\EditsBlocksContract;
use Ewk\MoonShineCms\Editor\InsertBlock;
use Ewk\MoonShineCms\Editor\LibraryCategory;
use Ewk\MoonShineCms\Editor\LibraryEntry;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\SharedBlockBlocksPage;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\SharedBlockFormPage;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\SharedBlockIndexPage;
use Ewk\MoonShineCms\Support\WriteRequest;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MoonShine\Contracts\Core\DependencyInjection\FieldsContract;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Core\Exceptions\ResourceException;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\AsyncMethod;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\SortDirection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The library of shared blocks: a block that lies once and shows on many pages.
 * It is an entity of its own and therefore an owner of blocks, which is what
 * gives it the draft, the publication and the statuses of any other owner for
 * free — the block editor of this section is the very same page the pages use.
 *
 * The section is a flat list with a search. Folders are out of the first
 * version: there will be a dozen records.
 *
 * @extends ModelResource<CmsSharedBlock, SharedBlockIndexPage, SharedBlockFormPage>
 */
#[Group('Content', 'document-text')]
#[Icon('rectangle-stack')]
#[Order(11)]
class SharedBlockResource extends ModelResource implements EditsBlocksContract
{
    use InteractsWithBlockEditor {
        insertCmsBlock as private packageInsertCmsBlock;
        moveCmsBlock as private packageMoveCmsBlock;
    }

    protected string $model = CmsSharedBlock::class;

    protected string $column = 'name';

    protected string $sortColumn = 'name';

    protected SortDirection $sortDirection = SortDirection::ASC;

    public function getTitle(): string
    {
        return (string) __('moonshine-cms::shared.title');
    }

    protected function pages(): array
    {
        return [
            SharedBlockIndexPage::class,
            SharedBlockFormPage::class,
            SharedBlockBlocksPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'code', 'name'];
    }

    /**
     * How many insertions a row has is a column of the list and a condition of
     * the form, so it is counted by the one query that reads the rows and not
     * once per row.
     *
     */
    protected function modifyQueryBuilder(Builder $builder): Builder
    {
        return $builder->withCount('insertions');
    }

    /**
     * Deleting an original while insertions live is refused: neither silent
     * emptiness nor a cascade over the pages. The model refuses it too; the
     * check is repeated here so that the editor reads how many insertions stand
     * in the way and what to do about them, instead of a bare failure of the
     * panel.
     *
     * The check sits in the one method both the row action and the mass
     * deletion go through.
     *
     * @param  DataWrapperContract<CmsSharedBlock>  $item
     *
     * @throws ResourceException
     */
    public function delete(DataWrapperContract $item, ?FieldsContract $fields = null): bool
    {
        $sharedBlock = $item->getOriginal();
        $insertions = self::insertionCount($sharedBlock);

        if ($insertions > 0) {
            throw new ResourceException(
                (string) __('moonshine-cms::shared.in_use_not_deletable', [
                    'name' => $sharedBlock->name,
                    'count' => $insertions,
                ]),
            );
        }

        return parent::delete($item, $fields);
    }

    /**
     * How many insertions the row has: the counted column of the list query
     * when it is there, a count of its own when the record was read another
     * way.
     */
    public static function insertionCount(CmsSharedBlock $sharedBlock): int
    {
        $counted = $sharedBlock->getAttribute('insertions_count');

        return is_numeric($counted) ? (int) $counted : $sharedBlock->insertions()->count();
    }

    /**
     * Unlinking every insertion of the record: the rows of type `shared` go
     * away, the content of the original is not copied anywhere and the original
     * itself stays. One transaction, so a half unlinked library entry cannot
     * happen.
     *
     * It rewrites rows, so it answers a POST and nothing else: an address
     * that unlinks on a GET is one `<img src>` away from doing it by itself,
     * see {@see WriteRequest}.
     */
    #[AsyncMethod]
    public function unlinkInsertions(Request $request, UnlinkSharedBlock $action): JsonResponse
    {
        $refused = WriteRequest::refuse($request);

        if ($refused instanceof JsonResponse) {
            return $refused;
        }

        $sharedBlock = CmsSharedBlock::query()->find($request->input('resourceItem'));

        if (! $sharedBlock instanceof CmsSharedBlock) {
            return new JsonResponse(
                ['message' => (string) __('moonshine-cms::shared.unknown_shared_block')],
                Response::HTTP_NOT_FOUND,
            );
        }

        $removed = $action->handle($sharedBlock);

        return new JsonResponse([
            'message' => (string) ($removed === 0
                ? __('moonshine-cms::shared.unlink_nothing')
                : __('moonshine-cms::shared.unlink_done', ['count' => $removed])),
            'removed' => $removed,
            'redirect' => $this->getFormPageUrl($sharedBlock->id),
        ]);
    }

    /**
     * Which block types the library of this owner offers. «May the block be put
     * into the shared block library» is a part of the placement declaration,
     * and it decides what the entry itself is — so it narrows the root level
     * only, while a slot of a container inside the entry is governed by the
     * slot, exactly as anywhere else.
     *
     * The shared group is never offered at any level: a reference to a shared
     * block inside a shared block is forbidden, which removes cycles
     * altogether.
     *
     * An entry that already holds its root block accepts nothing more there, so
     * the modal is empty instead of showing cards that answer 422.
     *
     * @param  list<LibraryCategory>  $categories
     * @return list<LibraryCategory>
     */
    protected function cmsNarrowLibrary(array $categories, Request $request): array
    {
        $root = ! is_numeric($request->input('parent'));

        if ($root && $this->hasRootBlock()) {
            return [];
        }

        return $this->allowedCategories($categories, $root);
    }

    /**
     * The same two rules on the server: a card hidden in the modal is a hint, a
     * crafted request is the real check. The insertion of a shared block into a
     * shared block is refused by the core; what belongs here is the shareable
     * declaration of the root and the single root.
     */
    #[AsyncMethod]
    public function insertCmsBlock(Request $request, InsertBlock $action): JsonResponse
    {
        if (is_numeric($request->input('parent'))) {
            return $this->packageInsertCmsBlock($request, $action);
        }

        $code = $request->input('code');

        if (is_string($code) && $code !== '' && ! $this->isShareable($code)) {
            return new JsonResponse(
                ['message' => (string) __('moonshine-cms::shared.not_shareable')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($this->hasRootBlock()) {
            return new JsonResponse(
                ['message' => (string) __('moonshine-cms::shared.single_root')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->packageInsertCmsBlock($request, $action);
    }

    /**
     * The single root is a rule of the storage and not of the modal, so a card
     * dragged out of a container to the root level is refused the same way an
     * insertion there is: the drop is the other way to get a second root.
     */
    #[AsyncMethod]
    public function moveCmsBlock(Request $request, MoveBlock $action): JsonResponse
    {
        $block = $this->cmsBlockOfRequest($request);

        if ($block instanceof CmsBlock
            && ! is_numeric($request->input('parent'))
            && $this->hasRootBlock(except: $block->id)
        ) {
            return new JsonResponse(
                ['message' => (string) __('moonshine-cms::shared.single_root')],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->packageMoveCmsBlock($request, $action);
    }

    /**
     * @param  list<LibraryCategory>  $categories
     * @return list<LibraryCategory>
     */
    private function allowedCategories(array $categories, bool $root): array
    {
        $allowed = [];

        foreach ($categories as $category) {
            if ($category->code === LibraryCategory::SHARED) {
                continue;
            }

            $entries = $root
                ? array_values(array_filter(
                    $category->entries,
                    fn (LibraryEntry $entry): bool => $this->isShareable($entry->code),
                ))
                : $category->entries;

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

    private function isShareable(string $code): bool
    {
        $registry = $this->getCore()->getContainer(BlockRegistryInterface::class);

        return $registry->has($code) && $registry->schemaFor($code)->isShareable();
    }

    /**
     * A shared block holds exactly one root block: a second one would turn one
     * insertion into several blocks and break the «one row is one block of the
     * envelope» correspondence.
     *
     * A move asks about every root but the card it is moving: putting the
     * root of an entry back where it already is stays a reorder.
     */
    private function hasRootBlock(?int $except = null): bool
    {
        $owner = $this->blockEditorOwner();

        $query = CmsBlock::query()
            ->where('blockable_type', $owner->getMorphClass())
            ->where('blockable_id', $owner->getKey())
            ->whereNull('parent_id');

        if ($except !== null) {
            $query->whereKeyNot($except);
        }

        return $query->exists();
    }
}
