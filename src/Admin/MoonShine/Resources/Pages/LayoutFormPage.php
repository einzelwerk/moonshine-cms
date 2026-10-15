<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Layouts\Layouts\LayoutCatalog;
use Ewk\Cms\Layouts\Models\CmsFooter;
use Ewk\Cms\Layouts\Models\CmsHeader;
use Ewk\Cms\Layouts\Models\CmsLayout;
use Ewk\MoonShineCms\MoonShine\Components\LocaleScope;
use Ewk\MoonShineCms\MoonShine\Fields\TranslatableText;
use Ewk\MoonShineCms\MoonShine\Resources\LayoutResource;
use Ewk\MoonShineCms\Support\Translated;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;

/**
 * The settings of one shell.
 *
 * The code is not on this form at all: it is the contract with the component
 * of the frontend, and the set of codes is declared by the installation, see
 * {@see LayoutCatalog}. What is left is what an editor actually decides, which
 * header and footer the shell uses, how wide its column is and whether the
 * pages that choose nothing get it.
 *
 * The service class of the body is gone from the form for the same reason: it
 * is a word of the stylesheet of the frontend, not a setting of the content.
 * The column stays, and what is in it stays with it.
 *
 * @extends FormPage<LayoutResource>
 */
class LayoutFormPage extends FormPage
{
    /**
     * @return iterable<ComponentContract>
     */
    protected function fields(): iterable
    {
        return [
            LocaleScope::wrap([
                $this->nameField()->switcher()->hint($this->description()),
                Select::make(__('moonshine-cms::chrome.header'), 'cms_header_id')
                    ->options($this->chromeOptions(array_values(CmsHeader::query()->orderBy('id')->get()->all())))
                    ->nullable()
                    ->native(),
                Select::make(__('moonshine-cms::chrome.footer'), 'cms_footer_id')
                    ->options($this->chromeOptions(array_values(CmsFooter::query()->orderBy('id')->get()->all())))
                    ->nullable()
                    ->native(),
                Select::make(__('moonshine-cms::chrome.container'), 'container')
                    ->options($this->containerOptions())
                    ->nullable()
                    ->hint(__('moonshine-cms::chrome.container_hint'))
                    ->native(),
                Switcher::make(__('moonshine-cms::chrome.is_default'), 'is_default')
                    ->hint(__('moonshine-cms::chrome.is_default_hint')),
                Switcher::make(__('moonshine-cms::fields.active'), 'is_active')->default(true),
            ], stateKey: 'cms-layout-form'),
        ];
    }

    public function getTitle(): string
    {
        $layout = $this->currentLayout();

        if ($layout === null) {
            return (string) __('moonshine-cms::chrome.layouts');
        }

        return (string) __('moonshine-cms::chrome.layout_title_edit', ['name' => $this->caption($layout)]);
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
     * @param  DataWrapperContract<CmsLayout>  $item
     */
    protected function rules(DataWrapperContract $item): array
    {
        return [
            ...$this->nameField()->rules(),
            'cms_header_id' => ['nullable', 'integer', 'exists:cms_headers,id'],
            'cms_footer_id' => ['nullable', 'integer', 'exists:cms_footers,id'],
            'container' => ['nullable', 'string', Rule::in(array_keys($this->containerOptions()))],
        ];
    }

    /**
     * What the shell is for, in the words of the declaration. A row seeded
     * before the declaration says so instead of showing a blank line.
     */
    private function description(): string
    {
        $layout = $this->currentLayout();

        if ($layout === null) {
            return '';
        }

        $declared = $this->catalog()->get($layout->code);

        return $declared === null
            ? (string) __('moonshine-cms::chrome.layout_unknown')
            : $declared->description;
    }

    /**
     * @param  list<CmsHeader|CmsFooter>  $records
     * @return array<int, string>
     */
    private function chromeOptions(array $records): array
    {
        $options = [];

        foreach ($records as $record) {
            $caption = Translated::caption($record->getAttribute('name'));

            $options[$record->id] = $caption !== '' ? $caption : $record->code;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function containerOptions(): array
    {
        $configured = $this->getCore()->getContainer(Repository::class)->get('moonshine-cms.container_widths', []);

        $options = [];

        foreach (is_array($configured) ? $configured : [] as $width) {
            if (! is_string($width) || $width === '') {
                continue;
            }

            $label = __('moonshine-cms::chrome.containers.'.$width);

            $options[$width] = is_string($label) && $label !== 'moonshine-cms::chrome.containers.'.$width
                ? $label
                : $width;
        }

        return $options;
    }

    private function caption(CmsLayout $layout): string
    {
        $caption = Translated::caption($layout->getAttribute('name'));

        if ($caption !== '') {
            return $caption;
        }

        $declared = $this->catalog()->get($layout->code);

        return $declared === null ? $layout->code : $declared->title;
    }

    private function currentLayout(): ?CmsLayout
    {
        $item = $this->getResource()?->getItem();

        return $item instanceof CmsLayout && $item->exists ? $item : null;
    }

    private function catalog(): LayoutCatalog
    {
        return $this->getCore()->getContainer(LayoutCatalog::class);
    }

    private function nameField(): TranslatableText
    {
        return TranslatableText::make(__('moonshine-cms::fields.name'), 'name')->required();
    }
}
