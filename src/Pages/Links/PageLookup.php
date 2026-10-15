<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Links;

use Ewk\Cms\Blocks\Links\Link;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PageModel;
use Illuminate\Container\Container;

/**
 * The pages links point at, read once per request.
 *
 * A link stores the uuid of its page, so every link of a chrome is a read of
 * `cms_pages` — forty of them for a handful of distinct pages, and a read of
 * the blocks of the catalog for every link with filters into it. Here each uuid
 * is read once, and a whole menu in a single query when its links are handed
 * over first, see {@see \Ewk\Cms\Blocks\Links\PreloadsLinks}.
 *
 * **The lookup is bound `scoped`**, never as a singleton: under Octane a
 * worker serves thousands of requests, and a page switched off or renamed
 * has to stop leading anywhere on the very next one. The resolvers that use
 * it are registered once per worker, so they take it from the container at
 * the moment of the call and hold nothing themselves.
 *
 * Within a request a write still happens — the admin saves a page and then
 * renders a form full of links, a test switches a page off between two
 * calls of one application — so the provider of the package forgets
 * everything read on every save or delete of a page or a block, see
 * {@see self::forget()}.
 *
 * Rows are kept as read, switched off and template pages included: what is
 * reachable is decided by each resolver, as it was when each of them ran its
 * own query.
 */
final class PageLookup
{
    /**
     * Every uuid asked for, a miss included: a link to a deleted page is
     * looked up once and not once per menu it appears in.
     *
     * @var array<string, CmsPage|null>
     */
    private array $pages = [];

    /**
     * Uuids handed over by {@see self::preload()} and not read yet.
     *
     * @var array<string, true>
     */
    private array $pending = [];

    /**
     * The active blocks of a page, by the key of the page.
     *
     * @var array<int|string, list<CmsBlock>>
     */
    private array $blocks = [];

    /**
     * The lookup of the request being served, taken at the moment of the
     * call: the container of an Octane request is a sandbox of its own, and
     * a lookup captured earlier would be the one of another request.
     */
    public static function current(): self
    {
        return Container::getInstance()->make(self::class);
    }

    /**
     * The pages a list of links of the `page` and `list` kinds point at, both
     * kinds storing the uuid under the same attribute.
     *
     * @param  list<Link>  $links
     */
    public function preloadTargetsOf(array $links): void
    {
        $uuids = [];

        foreach ($links as $link) {
            $uuid = $link->attribute('page');

            if (is_string($uuid)) {
                $uuids[] = $uuid;
            }
        }

        $this->preload($uuids);
    }

    /**
     * Notes the uuids to read together with the next page asked for. Nothing
     * is read here: the resolvers of both kinds hand their links over one
     * after the other, and the first page any of them asks for reads the
     * targets of all of them in one query.
     *
     * @param  iterable<string>  $uuids
     */
    public function preload(iterable $uuids): void
    {
        foreach ($uuids as $uuid) {
            $uuid = trim($uuid);

            if ($uuid !== '' && ! array_key_exists($uuid, $this->pages)) {
                $this->pending[$uuid] = true;
            }
        }
    }

    /**
     * The row of the page with this uuid, whatever its state, or null.
     */
    public function page(string $uuid): ?CmsPage
    {
        $uuid = trim($uuid);

        if ($uuid === '') {
            return null;
        }

        if (array_key_exists($uuid, $this->pages)) {
            return $this->pages[$uuid];
        }

        unset($this->pending[$uuid]);

        if ($this->pending !== []) {
            $this->readPending($uuid);
        }

        if (! array_key_exists($uuid, $this->pages)) {
            $this->pages[$uuid] = PageModel::query()->where('uuid', $uuid)->first();
        }

        return $this->pages[$uuid];
    }

    /**
     * The live active rows of the page, in their order, see
     * {@see ListLinkResolver}: a block added but not published yet is still
     * the block the editor is pointing at.
     *
     * @return list<CmsBlock>
     */
    public function activeBlocksOf(CmsPage $page): array
    {
        $key = $page->getKey();

        if (! is_int($key) && ! is_string($key)) {
            return $this->readBlocks($page);
        }

        return $this->blocks[$key] ??= $this->readBlocks($page);
    }

    /**
     * Everything read so far is dropped; the next question reads again.
     */
    public function forget(): void
    {
        $this->pages = [];
        $this->pending = [];
        $this->blocks = [];
    }

    /**
     * Every uuid noted by {@see self::preload()} and this one, in one query. A
     * uuid no row carries is remembered as a miss, exactly what the query of
     * one page would conclude.
     */
    private function readPending(string $uuid): void
    {
        $wanted = array_keys([$uuid => true] + $this->pending);
        $this->pending = [];
        $found = [];

        foreach (PageModel::query()->whereIn('uuid', $wanted)->get() as $page) {
            $this->pages[$page->uuid] = $page;
            $found[strtolower($page->uuid)] = true;
        }

        foreach ($wanted as $key) {
            $key = (string) $key;

            // A uuid the database matched under another spelling — a case
            // insensitive collation does that — is left for page() to ask
            // the way it always asked, so the answer stays the database's.
            if (! array_key_exists($key, $this->pages) && ! isset($found[strtolower($key)])) {
                $this->pages[$key] = null;
            }
        }
    }

    /**
     * @return list<CmsBlock>
     */
    private function readBlocks(CmsPage $page): array
    {
        /** @var list<CmsBlock> $blocks */
        $blocks = $page->blocks()->where('is_active', true)->get()->all();

        return $blocks;
    }
}
