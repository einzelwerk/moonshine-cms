<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources;

use Ewk\Cms\Layouts\Models\CmsLayout;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\LayoutFormPage;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\LayoutIndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;
use MoonShine\Support\Enums\Action;
use MoonShine\Support\Enums\SortDirection;
use MoonShine\Support\ListOf;

/**
 * The shells a page can be drawn in. A layout is a record with a code and not a
 * constructor: the code is the contract with the frontend, and everything else
 * is the width of the container, a service class and which header and footer
 * are used.
 *
 * The dictionary of codes is closed and grows with the frontend: a code
 * without a component there is an unkept promise, not a setting. So the set is
 * declared in code by the installation, and the panel neither creates nor
 * deletes a shell, it only configures the declared ones.
 *
 * @extends ModelResource<CmsLayout, LayoutIndexPage, LayoutFormPage>
 */
#[Group('Content', 'document-text')]
#[Icon('squares-2x2')]
#[Order(12)]
class LayoutResource extends ModelResource
{
    protected bool $usePagination = false;

    protected string $model = CmsLayout::class;

    /**
     * The list names the header and the footer of every shell, so both come
     * with the query: an application that forbids lazy loading is right to,
     * and a column is no reason for a query per row.
     */
    protected array $with = ['cmsHeader', 'cmsFooter'];

    protected string $column = 'code';

    protected string $sortColumn = 'sort_order';

    protected SortDirection $sortDirection = SortDirection::ASC;

    public function getTitle(): string
    {
        return (string) __('moonshine-cms::chrome.layouts');
    }

    protected function pages(): array
    {
        return [
            LayoutIndexPage::class,
            LayoutFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'code'];
    }

    /**
     * Neither created nor deleted in the panel: the set of shells is declared by the
     * installation in code, see {@see \Ewk\Cms\Layouts\Layouts\LayoutCatalog}. A
     * code without a component in the frontend would be an unkept promise, and a
     * deleted one would take the pages that name it with it.
     *
     * @return ListOf<Action>
     */
    protected function activeActions(): ListOf
    {
        return new ListOf(Action::class, [Action::VIEW, Action::UPDATE]);
    }
}
