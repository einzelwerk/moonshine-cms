<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Layouts\Chrome\ChromeKind;
use Ewk\Cms\Layouts\Chrome\ChromeSchemaRegistry;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\MoonShineCms\Actions\SaveChrome;
use Ewk\MoonShineCms\Adapters\SchemaContentForm;
use Ewk\MoonShineCms\Concerns\DeletesFromTheForm;
use Ewk\MoonShineCms\Contracts\ChromeResourceContract;
use Ewk\MoonShineCms\MoonShine\Components\LocaleScope;
use Ewk\MoonShineCms\MoonShine\Fields\TranslatableText;
use Ewk\MoonShineCms\MoonShine\Resources\MenuSlotResource;
use Ewk\MoonShineCms\Support\ChromeCode;
use Ewk\MoonShineCms\Support\Translated;
use Illuminate\Database\Eloquent\Model;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Fields\Relationships\MorphMany;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;
use RuntimeException;

/**
 * The form of a header and of a footer. They are twins: an own code, an own
 * caption, the values of the fields their kind declares, and the menus put into
 * their slots.
 *
 * **Neither is built out of blocks.** What is edited is the content of the
 * declared fields, never the structure: search, cart, the language switcher
 * and the overflow of the category row stay frontend code.
 *
 * The declared fields are rendered by the very adapter the block editor
 * uses, so no second way of turning a field declaration into a form
 * appears. Their values are deliberately excluded from the generic apply of
 * the panel and written by {@see SaveChrome} instead — see the reasons there.
 *
 * @extends FormPage<\Ewk\MoonShineCms\MoonShine\Resources\HeaderResource|\Ewk\MoonShineCms\MoonShine\Resources\FooterResource>
 */
class ChromeFormPage extends FormPage
{
    use DeletesFromTheForm;

    /**
     * @return iterable<ComponentContract>
     */
    protected function fields(): iterable
    {
        $tabs = [Tab::make(__('moonshine-cms::chrome.tab_main'), $this->mainFields())];

        $content = $this->contentFields();

        if ($content !== []) {
            $tabs[] = Tab::make(__('moonshine-cms::chrome.tab_content'), $content);
        }

        // The menus of the slots are a relation of the record, and the panel
        // draws a relation under the form and not inside a tab of it, see
        // MoonShine\Laravel\Pages\Crud\FormPage::bottomLayer(): a tab of its
        // own would have stayed empty with the table below it anyway. The
        // field is declared on the resource for the same reason: it belongs to
        // the record, not to this tab strip.
        return [
            LocaleScope::wrap(
                [Tabs::make($tabs)],
                stateKey: 'cms-chrome-form',
                slugAuto: ! $this->recordExists(),
            ),
        ];
    }

    /**
     * @return list<ComponentContract>
     */
    protected function mainFields(): array
    {
        return [
            $this->nameField()->switcher()->slugSource(),
            Text::make(__('moonshine-cms::fields.code'), 'code')
                ->nullable()
                ->customAttributes(['data-cms-slug-target' => 'code'])
                ->hint(__('moonshine-cms::chrome.code_hint')),
            Switcher::make(__('moonshine-cms::fields.active'), 'is_active')->default(true),
        ];
    }

    /**
     * The record as the panel calls it: «Header: Main header» instead of the
     * bare «edit».
     */
    public function getTitle(): string
    {
        $kind = $this->chromeKind()->value;
        $item = $this->getResource()?->getItem();

        if (! $item instanceof Model || ! $item->exists) {
            return (string) __('moonshine-cms::chrome.'.$kind.'_title_new');
        }

        $caption = Translated::caption($item->getAttribute('name'));
        $code = $item->getAttribute('code');

        return (string) __('moonshine-cms::chrome.'.$kind.'_title_edit', [
            'name' => $caption !== '' ? $caption : (is_string($code) ? $code : ''),
        ]);
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
     * The code is built from the name while the record is created and never
     * rewritten by a rename afterwards.
     */
    public function prepareForValidation(): void
    {
        ChromeCode::derive(
            request(),
            $this->getCore()->getContainer(LanguageCatalog::class)->defaultLocale(),
        );
    }

    /**
     * «Menus of the header» on a header and «menus of the footer» on a footer:
     * one caption for both would name a place the form is not about.
     */
    private function slotsTitle(): string
    {
        return (string) __('moonshine-cms::chrome.'.$this->chromeKind()->value.'_slots');
    }

    private function recordExists(): bool
    {
        $item = $this->getResource()?->getItem();

        return $item instanceof Model && $item->exists;
    }

    /**
     * @param  DataWrapperContract<Model>  $item
     */
    protected function rules(DataWrapperContract $item): array
    {
        return [
            'code' => ChromeCode::rules($item->getOriginal()->getTable(), $item->getKey()),
            ...$this->nameField()->rules(),
        ];
    }

    public function validationMessages(): array
    {
        return ChromeCode::messages();
    }

    protected function chromeKind(): ChromeKind
    {
        $resource = $this->getResource();

        if (! $resource instanceof ChromeResourceContract) {
            throw new RuntimeException('A chrome form belongs to a resource that names its chrome kind.');
        }

        return $resource->chromeKind();
    }

    private function nameField(): TranslatableText
    {
        return TranslatableText::make(__('moonshine-cms::fields.name'), 'name')->required();
    }

    /**
     * The declared content of this kind, detached from the apply of the
     * panel: {@see SaveChrome} owns the whole `content` column, see
     * {@see SchemaContentForm}.
     *
     * @return list<ComponentContract>
     */
    private function contentFields(): array
    {
        $registry = $this->getCore()->getContainer(ChromeSchemaRegistry::class);
        $kind = $this->chromeKind();

        if (! $registry->has($kind)) {
            return [];
        }

        $schema = $registry->schemaFor($kind);

        if ($schema->fields() === []) {
            return [];
        }

        return $this->getCore()->getContainer(SchemaContentForm::class)->fields($schema->toBlockSchema());
    }

    /**
     * Which menu stands in which place of this header or footer. Slots belong
     * to a row, and a row that does not exist yet owns nothing, so the panel
     * shows the table only once the record is saved, exactly as the block
     * editor of a page does.
     */
    protected function slotsField(): ComponentContract
    {
        return MorphMany::make($this->slotsTitle(), 'menuSlots', resource: MenuSlotResource::class)
            ->creatable()
            ->async()
            ->hint(__('moonshine-cms::chrome.slots_hint'));
    }

    /**
     * @return list<ComponentContract>
     */
    protected function bottomLayer(): array
    {
        $components = parent::bottomLayer();
        $item = $this->getResource()?->getItem();

        if (! $item instanceof Model || ! $item->exists) {
            return $components;
        }

        // Filled by hand, the way the panel fills the relations it collects
        // itself: the field is built here and not declared on the resource, so
        // nobody filled it before.
        $field = $this->slotsField();
        $filled = $field instanceof MorphMany
            ? $field->fillCast($item, $field->getResource()?->getCaster())
            : $field;

        // A box of its own and not a bare heading: under the buttons of the
        // form a heading sits on the last of them, and the table below it
        // reads as part of the form it is not.
        return [
            Box::make($this->slotsTitle(), [$filled]),
            ...$components,
        ];
    }
}
