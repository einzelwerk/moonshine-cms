<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Services;

use Ewk\Cms\Pages\Exceptions\InvalidPagePlacementException;
use Ewk\Cms\Pages\Exceptions\InvalidPageTypeException;
use Ewk\Cms\Pages\Exceptions\PageTypeCardinalityException;
use Ewk\Cms\Pages\Exceptions\PathCollisionException;
use Ewk\Cms\Pages\Exceptions\ReservedSegmentException;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PageLimits;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Support\PathNormalizer;
use Ewk\Cms\Pages\Support\ReservedSegments;
use Ewk\Cms\Pages\Support\Slug;
use Ewk\Cms\Pages\Types\Cardinality;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Structural writes of the page tree. Parent and position are the truth; the
 * path is recomputed from them, inside one transaction, together with every
 * check the address has to survive.
 *
 * The subtree is recomputed in PHP, node by node, from the parent's fresh
 * path plus the node's own slug — never by substituting a prefix in SQL.
 * Prefix substitution is the classic trap where `concert` captures
 * `concert-hall`, and its string expressions drift between dialects anyway.
 *
 * The same checks belong in the page form, because the address changes on an
 * ordinary slug edit too; {@see PageTree::rename()} is that path.
 */
final readonly class PageTree
{
    public function __construct(
        private PageTypeRegistry $types,
        private ReservedSegments $reserved,
    ) {
    }

    /**
     * Place a new page in the tree and store it.
     *
     * @param  int|null  $position  index among the siblings, appended when omitted
     */
    public function create(CmsPage $page, ?CmsPage $parent = null, ?int $position = null): CmsPage
    {
        return DB::transaction(function () use ($page, $parent, $position): CmsPage {
            $type = $this->types->get($page->type);

            Slug::assert($page->slug);
            $this->assertCardinality($type, $page);

            if ($type->hasFixedPath()) {
                $parent = null;
            } elseif ($parent !== null) {
                $this->assertParentAllowed($parent);
            }

            $page->parent_id = $parent?->id;
            $page->depth = $parent === null ? 0 : $parent->depth + 1;
            $path = $this->pathFor($type, $parent, $page->slug);

            $this->assertPathAcceptable($path);
            $this->assertDepth($path, $page->depth);
            $this->assertPathFree($path, []);

            $page->path = $path;
            $page->save();

            $this->sequence($page, $position);

            return $page;
        });
    }

    /**
     * Move a node under another parent, or reorder it under the current one.
     * The whole subtree follows: its paths are rewritten and its depth is
     * shifted. The old addresses are not kept — there is no address history
     * in the first version, and the previous address answers 404.
     *
     * @param  int|null  $position  index among the new siblings, appended when omitted
     */
    public function move(CmsPage $page, ?CmsPage $parent, ?int $position = null): CmsPage
    {
        return DB::transaction(function () use ($page, $parent, $position): CmsPage {
            $type = $this->types->get($page->type);

            if ($type->hasFixedPath()) {
                throw InvalidPagePlacementException::fixedPath($type->key());
            }

            if ($parent !== null) {
                if ($parent->is($page)) {
                    throw InvalidPagePlacementException::intoItself($page->path);
                }

                if (PathNormalizer::isDescendantOf($parent->path, $page->path)) {
                    throw InvalidPagePlacementException::intoDescendant($page->path, $parent->path);
                }

                $this->assertParentAllowed($parent);
            }

            $page->parent_id = $parent?->id;
            $page->depth = $parent === null ? 0 : $parent->depth + 1;

            $this->relocate($page, PathNormalizer::join($parent?->path, $page->slug));

            $page->save();
            $this->sequence($page, $position);

            return $page;
        });
    }

    /**
     * Change the slug of a page. The address of every descendant is rewritten
     * with it, because the path is derived and not stored twice.
     */
    public function rename(CmsPage $page, string $slug): CmsPage
    {
        return DB::transaction(function () use ($page, $slug): CmsPage {
            Slug::assert($slug);

            $type = $this->types->get($page->type);
            $page->slug = $slug;

            if ($type->hasFixedPath()) {
                // The address is written in the declaration; the slug is only
                // the identity of the page inside the admin panel.
                $page->save();

                return $page;
            }

            $parent = $page->parent_id === null ? null : PageModel::query()->find($page->parent_id);

            $this->relocate($page, PathNormalizer::join($parent?->path, $slug));
            $page->save();

            return $page;
        });
    }

    /**
     * The subtree of a page in one query by path prefix. Pages whose address
     * comes from their type declaration are not part of anybody's subtree.
     *
     * @return Collection<int, CmsPage>
     */
    public function descendants(CmsPage $page): Collection
    {
        return PageModel::query()
            ->underPath($page->path)
            ->whereNotIn('type', $this->fixedPathTypes())
            ->orderBy('depth')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * The ancestors of a page in one query by the list of their paths, no
     * recursion.
     *
     * @return Collection<int, CmsPage>
     */
    public function ancestors(CmsPage $page): Collection
    {
        $paths = [];
        $current = '';

        foreach (PathNormalizer::segments($page->path) as $segment) {
            $current .= PathNormalizer::ROOT.$segment;

            if ($current !== $page->path) {
                $paths[] = $current;
            }
        }

        if ($paths === []) {
            return new Collection();
        }

        return PageModel::query()->whereIn('path', $paths)->orderBy('depth')->get();
    }

    /**
     * Recompute the address of a node and of its whole subtree, validating
     * every one of them before a single row is written.
     */
    private function relocate(CmsPage $page, string $newPath): void
    {
        $descendants = $this->descendants($page);

        /** @var list<int> $subtreeIds */
        $subtreeIds = [$page->id];

        foreach ($descendants as $descendant) {
            $subtreeIds[] = $descendant->id;
        }

        $this->assertPathAcceptable($newPath);
        $this->assertDepth($newPath, $page->depth);
        $this->assertPathFree($newPath, $subtreeIds);

        $page->path = $newPath;

        /** @var array<int, list<CmsPage>> $byParent */
        $byParent = [];

        foreach ($descendants as $node) {
            $byParent[(int) $node->parent_id][] = $node;
        }

        $queue = [$page];

        while ($queue !== []) {
            $current = array_shift($queue);

            foreach ($byParent[$current->id] ?? [] as $child) {
                $child->path = PathNormalizer::join($current->path, $child->slug);
                $child->depth = $current->depth + 1;

                $this->assertPathAcceptable($child->path);
                $this->assertDepth($child->path, $child->depth);
                $this->assertPathFree($child->path, $subtreeIds);

                $queue[] = $child;
            }
        }

        foreach ($descendants as $node) {
            if ($node->isDirty()) {
                $node->save();
            }
        }
    }

    private function pathFor(PageType $type, ?CmsPage $parent, string $slug): string
    {
        if (! $type->hasFixedPath()) {
            return PathNormalizer::join($parent?->path, $slug);
        }

        return $type->path() ?? throw InvalidPageTypeException::missingPath($type->key());
    }

    /**
     * @throws ReservedSegmentException
     * @throws InvalidPagePlacementException
     */
    private function assertPathAcceptable(string $path): void
    {
        if (! PathNormalizer::withinLimit($path)) {
            throw InvalidPagePlacementException::pathTooLong($path);
        }

        $segments = PathNormalizer::segments($path);

        if ($segments !== [] && $this->reserved->isLanguageCode($segments[0])) {
            throw ReservedSegmentException::forLanguageCode($path, $segments[0]);
        }

        $conflict = $this->reserved->conflict($path);

        if ($conflict !== null) {
            throw ReservedSegmentException::forPath($path, $conflict);
        }
    }

    private function assertDepth(string $path, int $depth): void
    {
        if ($depth > PageLimits::MAX_DEPTH) {
            throw InvalidPagePlacementException::depthExceeded($path, $depth);
        }
    }

    /**
     * @param  list<int>  $exceptIds  keys of the moving subtree, which is allowed to give up its own paths
     */
    private function assertPathFree(string $path, array $exceptIds): void
    {
        $query = PageModel::query()->where('path', $path);

        if ($exceptIds !== []) {
            $query->whereNotIn('id', $exceptIds);
        }

        if ($query->exists()) {
            throw PathCollisionException::forPath($path);
        }
    }

    private function assertParentAllowed(CmsPage $parent): void
    {
        if ($this->types->get($parent->type)->isTemplate()) {
            throw InvalidPagePlacementException::underTemplate($parent->path);
        }
    }

    /**
     * @throws PageTypeCardinalityException
     */
    private function assertCardinality(PageType $type, CmsPage $page): void
    {
        if ($type->cardinality() === Cardinality::MANY) {
            return;
        }

        $query = PageModel::query()->where('type', $type->key());

        if ($page->exists) {
            $query->whereKeyNot($page->id);
        }

        // A template type holds one row, active or not: its address is the path
        // template of the declaration, and the path column is unique, so a
        // second row could not be stored anyway. The rule of one active
        // template per type is therefore kept by the row count and not by the
        // flag.
        if ($type->isTemplate()) {
            if ($query->exists()) {
                throw PageTypeCardinalityException::secondTemplate($type->key());
            }

            return;
        }

        if ($query->exists()) {
            throw PageTypeCardinalityException::secondSingleton($type->key());
        }
    }

    /**
     * Reindex the positions of a sibling group with the page at its place.
     */
    private function sequence(CmsPage $page, ?int $position): void
    {
        // A template is not a sibling of anything: it answers a pattern, not
        // an address, and the tree of the admin panel does not show it. A
        // position counted among the visible siblings must land where the
        // editor dropped the node, so templates stay out of the group.
        $query = PageModel::query()
            ->whereKeyNot($page->id)
            ->whereNotIn('type', $this->templateTypes());

        if ($page->parent_id === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $page->parent_id);
        }

        /** @var list<CmsPage> $siblings */
        $siblings = $query->orderBy('sort_order')->orderBy('id')->get()->all();

        $index = max(0, min($position ?? count($siblings), count($siblings)));
        array_splice($siblings, $index, 0, [$page]);

        foreach ($siblings as $order => $sibling) {
            if ($sibling->sort_order === $order) {
                continue;
            }

            $sibling->sort_order = $order;
            $sibling->save();
        }
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

    /**
     * @return list<string>
     */
    private function fixedPathTypes(): array
    {
        $keys = [];

        foreach ($this->types->all() as $key => $type) {
            if ($type->hasFixedPath()) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
