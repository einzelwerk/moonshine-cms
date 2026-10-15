<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources;

use Ewk\Cms\Layouts\Chrome\ChromeKind;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\MoonShineCms\Actions\SaveChrome;
use Ewk\MoonShineCms\Contracts\ChromeResourceContract;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\ChromeFormPage;
use Ewk\MoonShineCms\MoonShine\Resources\Pages\ChromeIndexPage;
use MoonShine\Crud\Attributes\SaveHandler;
use MoonShine\Laravel\Resources\ModelResource;
use MoonShine\MenuManager\Attributes\Group;
use MoonShine\MenuManager\Attributes\Order;
use MoonShine\Support\Attributes\Icon;

/**
 * Variants of the header: own records with own codes rather than an option of a
 * layout, so there can be several and the layout picks the one it uses.
 *
 * @extends ModelResource<CmsHeader, ChromeIndexPage, ChromeFormPage>
 */
#[Group('Content', 'document-text')]
#[Icon('bars-3')]
#[Order(13)]
#[SaveHandler(SaveChrome::class)]
class HeaderResource extends ModelResource implements ChromeResourceContract
{
    protected bool $usePagination = false;

    protected string $model = CmsHeader::class;

    protected string $column = 'code';

    public function chromeKind(): ChromeKind
    {
        return ChromeKind::HEADER;
    }

    public function getTitle(): string
    {
        return (string) __('moonshine-cms::chrome.headers');
    }

    protected function pages(): array
    {
        return [
            ChromeIndexPage::class,
            ChromeFormPage::class,
        ];
    }

    protected function search(): array
    {
        return ['id', 'code'];
    }
}
