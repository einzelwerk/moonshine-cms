<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\MoonShine\Components;

use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Services\AssembleTemplate;
use Ewk\Cms\Pages\Support\PageLimits;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\MoonShineCms\MoonShine\Resources\PageResource;
use Ewk\MoonShineCms\Tree\PageTreeBuilder;
use Ewk\MoonShineCms\Tree\TemplateTile;
use MoonShine\AssetManager\InlineCss;
use MoonShine\Contracts\Core\PageContract;
use MoonShine\Support\AlpineJs;
use MoonShine\Support\Enums\JsEvent;
use MoonShine\UI\Components\MoonShineComponent;

/**
 * The tree of pages with drag and drop between levels: a flat table with
 * indents would have made the change of parent a modal of its own, and that is
 * the main gesture of the editor. Drag and drop is Alpine over the HTML5 API,
 * the way the block editor does it — no new JS dependency.
 *
 * Every drop posts to the resource, which asks the tree of the pages package
 * to move the node; the rule of a move lives there and never only here.
 */
final class PageTreeView extends MoonShineComponent
{
    public const FRAGMENT = 'cms-page-tree';

    protected string $view = 'moonshine-cms::components.page-tree';

    public function __construct(
        private readonly PageResource $resource,
        private readonly PageContract $page,
        private readonly PageTreeBuilder $builder,
    ) {
        parent::__construct();
    }

    public static function forResource(PageResource $resource, PageContract $page): self
    {
        return new self($resource, $page, app(PageTreeBuilder::class));
    }

    /**
     * @return list<InlineCss>
     */
    protected function assets(): array
    {
        $css = file_get_contents(__DIR__.'/../../../../resources/css/page-tree.css');

        return $css === false ? [] : [InlineCss::make($css)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        return [
            'nodes' => $this->builder->build($this->resource),
            'templates' => $this->templates(),
            'templatesHint' => (string) __('moonshine-cms::pages.templates_hint'),
            'fragmentEvent' => AlpineJs::event(JsEvent::FRAGMENT_UPDATED, self::FRAGMENT),
            'maxDepth' => PageLimits::MAX_DEPTH,
            'moveUrl' => $this->getCore()->getRouter()->getEndpoints()->method(
                'movePage',
                page: $this->page,
                resource: $this->resource,
            ),
            // What the client renders on its own cannot be translated by
            // blade, the way the block editor hands its own words over.
            'labels' => ['requestFailed' => (string) __('moonshine-cms::editor.request_failed')],
        ];
    }

    /**
     * The templates of the entity types, under the tree and not in a section of
     * their own: an editor looking for «the page of a tour» looks among the
     * pages. There is nothing to create and nothing to delete here, because the
     * set of types is code, so a tile carries exactly two states and one
     * button.
     *
     * @return list<TemplateTile>
     */
    private function templates(): array
    {
        $assemble = $this->getCore()->getContainer(AssembleTemplate::class);
        $tiles = [];

        foreach ($this->getCore()->getContainer(PageTypeRegistry::class)->all() as $type) {
            if (! $type->isTemplate()) {
                continue;
            }

            $page = $assemble->existing($type->key());

            $tiles[] = new TemplateTile(
                type: $type->key(),
                title: $type->title(),
                description: $this->describe($type),
                status: $this->status($page),
                tone: $this->tone($page),
                editorUrl: $page instanceof CmsPage ? $this->resource->blockEditorPageUrl($page->id) : '',
                assembleUrl: $page instanceof CmsPage ? '' : $this->assembleUrl($type),
            );
        }

        return $tiles;
    }

    /**
     * What the template stands for, with an address of the shape its entities
     * get. The placeholders of the declared pattern are shown filled in rather
     * than in braces: `/tours/{tour}` is the grammar of the declaration, and an
     * editor reads the address, not the grammar.
     */
    private function describe(PageType $type): string
    {
        $pattern = $type->pattern();

        if ($pattern === null) {
            return (string) __('moonshine-cms::pages.template_description_short');
        }

        $sample = (string) __('moonshine-cms::pages.template_path_placeholder');
        $values = [];

        foreach ($pattern->placeholders() as $placeholder) {
            $values[$placeholder] = $sample;
        }

        return (string) __('moonshine-cms::pages.template_description', [
            'path' => $pattern->build($values) ?? $type->path(),
        ]);
    }

    private function status(?CmsPage $page): string
    {
        if (! $page instanceof CmsPage) {
            return (string) __('moonshine-cms::pages.template_missing');
        }

        return (string) ($page->is_active
            ? __('moonshine-cms::pages.template_ready')
            : __('moonshine-cms::pages.template_draft'));
    }

    private function tone(?CmsPage $page): string
    {
        if (! $page instanceof CmsPage) {
            return 'gray';
        }

        return $page->is_active ? 'green' : 'yellow';
    }

    /**
     * Assembling writes a row, so the tile posts and asks first, see
     * {@see PageResource::assembleTemplate()}.
     */
    private function assembleUrl(PageType $type): string
    {
        return $this->getCore()->getRouter()->getEndpoints()->method(
            'assembleTemplate',
            params: ['type' => $type->key()],
            page: $this->page,
            resource: $this->resource,
        );
    }
}
