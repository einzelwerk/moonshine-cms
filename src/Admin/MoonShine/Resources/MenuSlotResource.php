<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources;

use Ewk\Cms\Layouts\Models\CmsMenuSlotAssignment;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\MenuSlotFormPage;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\MenuSlotIndexPage;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\SkipMenu;

/**
 * A menu put into a slot of a header or of a footer. Slots belong to the header
 * and the footer rather than to the layout, because the place in the markup is
 * theirs, so these rows are edited from their forms and nowhere else.
 *
 * @extends ModelResource<CmsMenuSlotAssignment, MenuSlotIndexPage, MenuSlotFormPage>
 */
#[SkipMenu]
class MenuSlotResource extends ModelResource
{
    protected bool $usePagination = false;

    protected string $model = CmsMenuSlotAssignment::class;

    protected string $column = 'slot';

    protected array $with = ['cmsMenu'];

    public function getTitle(): string
    {
        return (string) __('moonshine-cms::chrome.slots');
    }

    protected function pages(): array
    {
        return [
            MenuSlotIndexPage::class,
            MenuSlotFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'slot'];
    }
}
