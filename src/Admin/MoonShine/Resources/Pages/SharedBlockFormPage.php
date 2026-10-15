<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Blocks\Models\CmsSharedBlock;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\MoonShineCms\Concerns\DeletesFromTheForm;
use Ewk\MoonShineCms\Contracts\EditsBlocksContract;
use Ewk\MoonShineCms\MoonShine\Components\LocaleScope;
use Ewk\MoonShineCms\MoonShine\Components\SharedBlockUsages;
use Ewk\MoonShineCms\MoonShine\Resources\SharedBlockResource;
use Ewk\MoonShineCms\Support\ChromeCode;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * The settings of a library entry: the code, the name and the activity. The
 * content is not here but on the block editor page of the record, see
 * {@see SharedBlockBlocksPage} — a card of the editor is a form of its own.
 *
 * The name is a plain string and not a translatable value: `cms_shared_blocks`
 * stores one `name` column, because the caption is what an editor picks the
 * entry by in the library and never something a visitor reads.
 *
 * @extends FormPage<SharedBlockResource>
 */
class SharedBlockFormPage extends FormPage
{
    use DeletesFromTheForm;

    /**
     * @return list<ComponentContract>
     */
    protected function topLayer(): array
    {
        $components = parent::topLayer();
        $resource = $this->getResource();

        return $components;
    }

    /**
     * The switch between the settings and the blocks of this entry, drawn on
     * the line above the fields. Blocks belong to a row, and a row that does
     * not exist yet owns nothing, so it appears once the entry is saved.
     */
    private function switcher(): ?ComponentContract
    {
        $resource = $this->getResource();

        if (! $resource instanceof EditsBlocksContract || $resource->getItem() === null) {
            return null;
        }

        return $resource->blockEditorSwitcher(false);
    }

    /**
     * @return iterable<ComponentContract>
     */
    protected function fields(): iterable
    {
        $fields = [
            Text::make(__('moonshine-cms::fields.name'), 'name')
                ->required()
                ->customAttributes(['data-cms-slug-source' => 'name'])
                ->hint(__('moonshine-cms::shared.name_hint')),
            Text::make(__('moonshine-cms::fields.code'), 'code')
                ->nullable()
                ->customAttributes(['data-cms-slug-target' => 'code'])
                ->hint(__('moonshine-cms::shared.code_hint')),
            Switcher::make(__('moonshine-cms::fields.active'), 'is_active')->default(true),
        ];

        $sharedBlock = $this->currentSharedBlock();

        if ($sharedBlock === null) {
            return $this->scoped($fields);
        }

        // Where the entry is inserted, and the one way out of a refused
        // deletion. The list already reads the insertions, so it carries the
        // button too and nothing asks the storage a second time whether there
        // is anything to unlink.
        $fields[] = SharedBlockUsages::forSharedBlock($sharedBlock, $this->unlinkUrl($sharedBlock));

        return $this->scoped($fields);
    }

    /**
     * The name of a library entry is one string and not a dictionary, so the
     * form has nothing to switch languages between; the scope is still here,
     * because the code is built from the name while the entry is created.
     *
     * @param  list<ComponentContract>  $fields
     * @return list<ComponentContract>
     */
    private function scoped(array $fields): array
    {
        return [
            LocaleScope::wrap(
                $fields,
                stateKey: 'cms-shared-block-form',
                slugAuto: $this->currentSharedBlock() === null,
                switch: false,
                lead: $this->switcher(),
            ),
        ];
    }

    public function getTitle(): string
    {
        $sharedBlock = $this->currentSharedBlock();

        return $sharedBlock === null
            ? (string) __('moonshine-cms::shared.title_new')
            : (string) __('moonshine-cms::shared.title_edit', ['name' => $sharedBlock->name]);
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        $breadcrumbs = parent::getBreadcrumbs();
        $breadcrumbs[$this->getRoute()] = $this->getTitle();

        return $breadcrumbs;
    }

    /**
     * The code is built from the name while the entry is created, stays visible
     * and survives a rename.
     */
    public function prepareForValidation(): void
    {
        ChromeCode::derive(
            request(),
            $this->getCore()->getContainer(LanguageCatalog::class)->defaultLocale(),
        );
    }

    /**
     * @param  DataWrapperContract<CmsSharedBlock>  $item
     */
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ChromeCode::rules('cms_shared_blocks', $item->getKey()),
        ];
    }

    public function validationMessages(): array
    {
        return ChromeCode::messages();
    }

    /**
     * The address of «unlink everything». It answers a POST only, so it is
     * sent by a button of the panel and never by an address that a page
     * somewhere manages to load, see {@see SharedBlockResource::unlinkInsertions()}.
     */
    private function unlinkUrl(CmsSharedBlock $sharedBlock): string
    {
        return $this->getCore()->getRouter()->getEndpoints()->method(
            'unlinkInsertions',
            params: ['resourceItem' => $sharedBlock->id],
            page: $this,
            resource: $this->getResource(),
        );
    }

    private function currentSharedBlock(): ?CmsSharedBlock
    {
        $item = $this->getResource()?->getItem();

        return $item instanceof CmsSharedBlock && $item->exists ? $item : null;
    }
}
