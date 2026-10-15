<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources;

use Ewk\Cms\Layouts\Chrome\ChromeKind;
use Ewk\Cms\Layouts\Models\CmsFooter;
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
 * Variants of the footer, the twin of {@see HeaderResource}.
 *
 * @extends ModelResource<CmsFooter, ChromeIndexPage, ChromeFormPage>
 */
#[Group('Content', 'document-text')]
#[Icon('bars-3-bottom-left')]
#[Order(14)]
#[SaveHandler(SaveChrome::class)]
class FooterResource extends ModelResource implements ChromeResourceContract
{
    protected bool $usePagination = false;

    protected string $model = CmsFooter::class;

    protected string $column = 'code';

    public function chromeKind(): ChromeKind
    {
        return ChromeKind::FOOTER;
    }

    public function getTitle(): string
    {
        return (string) __('moonshine-cms::chrome.footers');
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
