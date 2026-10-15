<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Sitemap;

use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The pages of the tree that open by their own address: the singletons and
 * the pages an editor created. A template is opened through its subject and
 * never by itself, so its addresses belong to {@see SubjectSitemapSource}.
 *
 * The set is the one the resolver answers 200 for: an active page of a declared
 * type with a published snapshot. A draft does not exist for a visitor and has
 * no place here.
 *
 * The date is the publication of the snapshot and not the last save of the
 * draft, or an edit of the metadata when that came later.
 */
final readonly class PageSitemapSource implements SitemapSource
{
    public const string KEY = 'pages';

    public function __construct(private PageTypeRegistry $types)
    {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    public function items(int $offset, int $limit): array
    {
        $pages = $this->query()
            ->orderBy(PageModel::make()->getQualifiedKeyName())
            ->offset($offset)
            ->limit($limit)
            ->get();

        /** @var list<int> $ids */
        $ids = $pages->modelKeys();
        $published = Moments::publications(CmsPage::MORPH_ALIAS, $ids);
        $edited = Moments::metadata(CmsPage::MORPH_ALIAS, $ids);

        $items = [];

        foreach ($pages as $page) {
            $items[] = new SitemapItem(
                $page->path,
                SitemapItem::latest($published[$page->id] ?? null, $edited[$page->id] ?? null),
            );
        }

        return $items;
    }

    /**
     * @return Builder<CmsPage>
     */
    private function query(): Builder
    {
        $query = PageModel::query();
        $pages = $query->getModel()->getTable();
        $versions = (new CmsVersion())->getTable();

        $query->where($pages.'.is_active', true)
            ->whereIn($pages.'.type', $this->addressableTypes())
            ->whereExists(static function (QueryBuilder $version) use ($versions, $pages): void {
                $version->selectRaw('1')
                    ->from($versions)
                    ->where($versions.'.owner_type', CmsPage::MORPH_ALIAS)
                    ->whereColumn($versions.'.owner_id', $pages.'.id');
            });

        Indexable::apply($query);

        return $query;
    }

    /**
     * The declared types whose pages carry an address of their own. A row of
     * a type the installation does not declare is not resolved either.
     *
     * @return list<string>
     */
    private function addressableTypes(): array
    {
        $keys = [];

        foreach ($this->types->all() as $key => $type) {
            if (! $type->isTemplate()) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
