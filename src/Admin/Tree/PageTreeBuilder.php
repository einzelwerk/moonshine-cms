<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Tree;

use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\MoonShineCms\MoonShine\Resources\PageResource;
use Ewk\MoonShineCms\Support\Translated;
use Illuminate\Support\Collection;

/**
 * The tree of the «Pages» section: the free pages an editor creates and deletes
 * plus the singletons, which are marked. Templates are not in it — they answer
 * a pattern, not an address, and have tiles of their own.
 *
 * Read in one query and grouped by parent in memory.
 */
final readonly class PageTreeBuilder
{
    public function __construct(private PageTypeRegistry $types)
    {
    }

    /**
     * @return list<PageNode>
     */
    public function build(PageResource $resource): array
    {
        /** @var Collection<int, CmsPage> $rows */
        $rows = PageModel::query()
            ->whereNotIn('type', $this->templateTypes())
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byParent = $rows->groupBy(static fn (CmsPage $page): int => $page->parent_id ?? 0);

        return $this->nodes($byParent, 0, 0, $resource);
    }

    /**
     * @param  Collection<int, Collection<int, CmsPage>>  $byParent
     * @return list<PageNode>
     */
    private function nodes(Collection $byParent, int $parentId, int $depth, PageResource $resource): array
    {
        $nodes = [];

        foreach ($byParent->get($parentId, new Collection()) as $page) {
            $type = $this->types->has($page->type) ? $this->types->get($page->type) : null;
            $caption = Translated::caption($page->getAttribute('name'));

            $nodes[] = new PageNode(
                id: $page->id,
                caption: $caption !== '' ? $caption : $page->slug,
                path: $page->path,
                type: $page->type,
                typeTitle: $type?->title() ?? $page->type,
                isActive: (bool) $page->is_active,
                fixed: $type?->hasFixedPath() ?? false,
                depth: $depth,
                formUrl: $resource->getFormPageUrl($page->id),
                blocksUrl: $resource->blockEditorPageUrl($page->id),
                addChildUrl: $resource->getFormPageUrl(params: ['parent' => $page->id]),
                children: $this->nodes($byParent, $page->id, $depth + 1, $resource),
            );
        }

        return $nodes;
    }

    /**
     * @return list<string>
     */
    private function templateTypes(): array
    {
        $keys = [];

        foreach ($this->types->all() as $key => $type) {
            if ($type->isTemplate()) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
