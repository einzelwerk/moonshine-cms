<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Resources\Pages;

use Ewk\Cms\Layouts\Layouts\LayoutCatalog;
use Ewk\Cms\Layouts\Models\CmsLayout;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Pages\Exceptions\InvalidSlugException;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PageLimits;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Support\PathNormalizer;
use Ewk\Cms\Pages\Support\Slug;
use Ewk\Cms\Pages\Types\Cardinality;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\MoonShineCms\Concerns\DeletesFromTheForm;
use Ewk\MoonShineCms\Concerns\InteractsWithSeoFields;
use Ewk\MoonShineCms\Contracts\EditsBlocksContract;
use Ewk\MoonShineCms\MoonShine\Components\LocaleScope;
use Ewk\MoonShineCms\MoonShine\Fields\TranslatableText;
use Ewk\MoonShineCms\MoonShine\Resources\PageResource;
use Ewk\MoonShineCms\Rules\PageTypeCardinalityRule;
use Ewk\MoonShineCms\Rules\PageTypeStaysFixedRule;
use Ewk\MoonShineCms\Support\Translated;
use Illuminate\Validation\Rule;
use MoonShine\Contracts\Core\TypeCasts\DataWrapperContract;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Crud\FormPage;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Switcher;
use MoonShine\UI\Fields\Text;

/**
 * The settings of a page. The editor of the blocks is not here but on a page
 * of its own, see {@see PageBlocksPage}: a card of the editor is a form, and
 * a form inside this one is invalid HTML.
 *
 * Not final: an installation adds its own tabs and rules by overriding
 * {@see tabs()} and {@see rules()} and calling the parent.
 *
 * @extends FormPage<PageResource>
 */
class PageFormPage extends FormPage
{
    use DeletesFromTheForm;
    use InteractsWithSeoFields;

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
     * The switch between the settings and the blocks of this page, drawn on
     * the line of the language switch above the fields. Blocks belong to a
     * row, and a row that does not exist yet owns nothing, so the switch
     * appears once the page is saved.
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
     * The form lives inside the language scope of the CMS: the caption shows
     * the language the switch selects, in the place the form declares it, and
     * the address is built from that caption while the page is created.
     *
     * @return iterable<ComponentContract>
     */
    protected function fields(): iterable
    {
        return [
            LocaleScope::wrap(
                [Tabs::make($this->tabs())],
                stateKey: 'cms-page-form',
                slugAuto: ! ($this->getResource()?->getItem() instanceof CmsPage),
                lead: $this->switcher(),
            ),
        ];
    }

    /**
     * @return list<Tab>
     */
    protected function tabs(): array
    {
        $item = $this->getResource()?->getItem();

        return [
            Tab::make(__('moonshine-cms::pages.tab_main'), $this->mainFields()),
            // The metadata of the page. An empty title falls back to the
            // caption of the page, and the form says so rather than leaving the
            // editor to guess. The whole form sits in one language scope
            // already, so the tab brings none of its own.
            $this->seoTab(
                $item,
                $item instanceof CmsPage ? Translated::caption($item->getAttribute('name')) : null,
                localeScope: false,
            ),
        ];
    }

    /**
     * What an editor fills on every page: what kind of page it is, how it is
     * called, where it lives and how it looks. The type opens the form rather
     * than hiding behind a tab of its own: it is chosen once, on an empty
     * form, and everything under it reads differently depending on the answer.
     *
     * @return list<ComponentContract>
     */
    protected function mainFields(): array
    {
        return [
            Select::make(__('moonshine-cms::pages.type'), 'type')
                ->options($this->typeOptions())
                ->default($this->defaultType())
                ->hint(__('moonshine-cms::pages.type_hint'))
                ->required()
                ->native(),
            $this->nameField()->switcher()->slugSource(),
            Text::make(__('moonshine-cms::fields.slug'), 'slug')
                ->nullable()
                ->customAttributes(['data-cms-slug-target' => 'slug'])
                ->hint(__('moonshine-cms::pages.slug_hint')),
            // Where the page hangs in the tree: the parent and the slug make
            // the address. The tree page drags nodes between levels; the form
            // is the other way in, and the one a freshly created page has.
            Select::make(__('moonshine-cms::pages.parent'), 'parent_id')
                ->options($this->parentOptions())
                ->default($this->requestedParent())
                ->nullable()
                ->customAttributes(['placeholder' => (string) __('moonshine-cms::pages.parent_none')])
                ->hint(__('moonshine-cms::pages.parent_hint'))
                ->native()
                ->canSee(fn (): bool => ! $this->itemHasFixedPath()),
            // Which shell the page is drawn in: the page names a layout by
            // code, and an empty choice means the default one.
            Select::make(__('moonshine-cms::pages.layout'), 'layout_code')
                ->options($this->layoutOptions())
                ->nullable()
                ->customAttributes(['placeholder' => (string) __('moonshine-cms::pages.layout_default')])
                ->hint(__('moonshine-cms::pages.layout_hint'))
                ->native(),
            Switcher::make(__('moonshine-cms::fields.active'), 'is_active')->default(true),
        ];
    }

    /**
     * The page as the panel calls it: the caption of the record instead of the
     * bare «edit», so a breadcrumb and a browser tab say which page is open.
     */
    public function getTitle(): string
    {
        $item = $this->getResource()?->getItem();

        if (! $item instanceof CmsPage || ! $item->exists) {
            return (string) __('moonshine-cms::pages.title_new');
        }

        $caption = Translated::caption($item->getAttribute('name'));

        return (string) __('moonshine-cms::pages.title_edit', [
            'name' => $caption !== '' ? $caption : $item->slug,
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

    protected function nameField(): TranslatableText
    {
        return TranslatableText::make(__('moonshine-cms::fields.name'), 'name')->required();
    }

    /**
     * @param  DataWrapperContract<CmsPage>  $item
     */
    protected function rules(DataWrapperContract $item): array
    {
        $page = $item->getOriginal();

        return [
            ...$this->nameField()->rules(),
            'slug' => [
                'nullable',
                'string',
                'max:'.PageLimits::SLUG_LENGTH,
                'regex:'.Slug::PATTERN,
            ],
            // absent on legacy submissions: the column defaults to a page of
            // the freely created type
            'type' => [
                'sometimes',
                'required',
                Rule::in(array_keys($this->registry()->all())),
                new PageTypeCardinalityRule($this->registry(), $page),
                new PageTypeStaysFixedRule($this->registry(), $page),
            ],
            'layout_code' => [
                'nullable',
                'string',
                Rule::in(array_keys($this->layoutOptions())),
            ],
            // A singleton has no parent to choose, and the value the form
            // may still carry is ignored by the save, see SavePage.
            'parent_id' => $this->itemHasFixedPath() ? ['nullable'] : [
                'nullable',
                'integer',
                Rule::in(array_keys($this->parentOptions())),
            ],
            ...$this->seoRules(),
        ];
    }

    /**
     * The pages a page may hang under: not a template, not itself and not
     * its own subtree, and not so deep that the page would cross the limit
     * of the tree. The tree refuses all of it too; the select simply does
     * not offer it.
     *
     * @return array<int, string>
     */
    protected function parentOptions(): array
    {
        $item = $this->getResource()?->getItem();
        // The root of a singleton is the prefix of every address; its
        // «subtree» is not one, so only a free page excludes its descendants.
        $own = $item instanceof CmsPage && $item->exists && ! $this->itemHasFixedPath() ? $item : null;
        $registry = $this->registry();
        $options = [];

        $candidates = PageModel::query()
            ->where('depth', '<', PageLimits::MAX_DEPTH)
            ->orderBy('path')
            ->get();

        foreach ($candidates as $candidate) {
            if ($registry->has($candidate->type) && $registry->get($candidate->type)->isTemplate()) {
                continue;
            }

            if ($own !== null && ($candidate->is($own) || PathNormalizer::isDescendantOf($candidate->path, $own->path))) {
                continue;
            }

            $caption = Translated::caption($candidate->getAttribute('name'));

            $options[$candidate->id] = ($caption !== '' ? $caption : $candidate->slug).' — '.$candidate->path;
        }

        return $options;
    }

    /**
     * «Add a child page» on the tree opens the form with the parent chosen.
     */
    private function requestedParent(): ?int
    {
        $parent = request()->query('parent');

        return is_numeric($parent) ? (int) $parent : null;
    }

    private function itemHasFixedPath(): bool
    {
        $item = $this->getResource()?->getItem();

        return $item instanceof CmsPage
            && $this->registry()->has($item->type)
            && $this->registry()->get($item->type)->hasFixedPath();
    }

    /**
     * The slug typed by an editor is normalized before validation, so that a
     * caption pasted into the field becomes a slug instead of a format error,
     * and an empty field takes its slug from the caption in the default
     * language. A slash inside is a word boundary: nesting lives in the tree,
     * not in the slug.
     */
    public function prepareForValidation(): void
    {
        $request = request();
        $default = $this->languages()->defaultLocale();

        $typed = $request->input('slug');
        $typed = is_string($typed) ? trim($typed, " \t\n\r/") : '';

        $name = $request->input('name');
        $caption = is_array($name) ? ($name[$default] ?? null) : null;
        $source = $typed !== '' ? $typed : (is_string($caption) ? $caption : '');

        if ($source === '') {
            return;
        }

        try {
            $slug = Slug::generate(str_replace(['/', '_'], '-', $source), $default);
        } catch (InvalidSlugException) {
            // Left as typed: the format rule reports it with a message.
            return;
        }

        $request->merge(['slug' => $slug]);
    }

    public function validationMessages(): array
    {
        return [
            'slug.regex' => __('moonshine-cms::pages.slug_kebab'),
        ];
    }

    /**
     * Captions of the declared types: the set of types is code, so the select
     * is built from the registry and never from a list of its own.
     *
     * @return array<string, string>
     */
    protected function typeOptions(): array
    {
        $options = [];

        foreach ($this->registry()->all() as $key => $type) {
            $options[$key] = $type->title();
        }

        return $options;
    }

    /**
     * The freely created type is the natural default of a new page: the
     * first declared type with the `many` cardinality.
     */
    private function defaultType(): ?string
    {
        foreach ($this->registry()->all() as $key => $type) {
            if ($type->cardinality() === Cardinality::MANY) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Codes of the active layouts. The integrity of the reference is held by
     * this form and not by a foreign key: the page stores the code, so that the
     * pages package does not start depending on the layouts one.
     *
     * @return array<string, string>
     */
    protected function layoutOptions(): array
    {
        $options = [];
        $catalog = $this->getCore()->getContainer(LayoutCatalog::class);

        foreach (CmsLayout::query()->active()->orderBy('sort_order')->orderBy('id')->get() as $layout) {
            // The caption an editor picks by, and never the code next to it:
            // the code is the contract with the frontend and says nothing to
            // the person choosing how a page looks.
            $name = Translated::caption($layout->name);
            $declared = $catalog->get($layout->code)?->title;

            $options[$layout->code] = $name !== '' ? $name : ($declared ?? $layout->code);
        }

        return $options;
    }

    protected function registry(): PageTypeRegistry
    {
        return $this->getCore()->getContainer(PageTypeRegistry::class);
    }

    private function languages(): LanguageCatalog
    {
        return $this->getCore()->getContainer(LanguageCatalog::class);
    }
}
