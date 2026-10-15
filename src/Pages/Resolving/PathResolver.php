<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\Resolving;

use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Support\PathNormalizer;
use Ewk\Cms\Pages\Support\PathPattern;
use Ewk\Cms\Pages\Support\ReservedSegments;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Ewk\Cms\Pages\Types\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns an address into content, the service behind `GET /api/v1/resolve`. The
 * HTTP route lives in the application: this package knows nothing about its
 * transport.
 *
 * The order is fixed:
 *
 * 1. normalize the address;
 * 2. a page by its `path` column;
 * 3. the path templates of page types with a subject — the entity by its
 *    slug, its visibility criterion, and the canonical address check;
 * 4. otherwise a miss.
 *
 * A language prefix is reported before any of it: it is an integration
 * mistake, not a missing page.
 */
final readonly class PathResolver
{
    public function __construct(
        private PageTypeRegistry $types,
        private ReservedSegments $reserved,
    ) {
    }

    public function resolve(string $path): ResolvedPath
    {
        $normalized = PathNormalizer::normalize($path);
        $segments = PathNormalizer::segments($normalized);

        if ($segments !== [] && $this->reserved->isLanguageCode($segments[0])) {
            return ResolvedPath::languagePrefix($normalized, $segments[0]);
        }

        // An address longer than the column could never have been stored, so
        // it cannot match a page, and no template pattern is that long.
        if (! PathNormalizer::withinLimit($normalized)) {
            return ResolvedPath::notFound($normalized);
        }

        $page = $this->pageAt($normalized);

        if ($page !== null) {
            return ResolvedPath::page($normalized, $page, $this->types->get($page->type));
        }

        foreach ($this->types->all() as $type) {
            $resolved = $this->resolveTemplate($type, $normalized, $segments);

            if ($resolved !== null) {
                return $resolved;
            }
        }

        return ResolvedPath::notFound($normalized);
    }

    /**
     * An active page addressable by its own path. A template page is not one of
     * them: it is opened through its subject, never by itself.
     */
    private function pageAt(string $path): ?CmsPage
    {
        $page = PageModel::query()
            ->where('path', $path)
            ->where('is_active', true)
            ->first();

        if ($page === null || ! $this->types->has($page->type)) {
            return null;
        }

        return $this->types->get($page->type)->isTemplate() ? null : $page;
    }

    /**
     * @param  list<string>  $segments
     */
    private function resolveTemplate(PageType $type, string $path, array $segments): ?ResolvedPath
    {
        if (! $type->isTemplate()) {
            return null;
        }

        $pattern = $type->pattern();
        $subject = $type->subject();
        $placeholder = $type->subjectPlaceholder();

        if ($pattern === null || $subject === null || $placeholder === null) {
            return null;
        }

        $values = $pattern->match($segments);

        if ($values === null || ! isset($values[$placeholder])) {
            return null;
        }

        $entity = $this->findSubject($subject, $values[$placeholder]);

        if ($entity === null || $this->canonicalPath($pattern, $subject, $entity, $values, $placeholder) !== $path) {
            return null;
        }

        $template = $this->templateOf($type);

        // A subject without a built template has no content to answer with;
        // the address is a miss until the template exists.
        return $template === null ? null : ResolvedPath::subject($path, $template, $type, $entity);
    }

    /**
     * The entity by the column the address carries, filtered by the visibility
     * criterion the type declares. The criterion is a scope called by its
     * declared name: every entity of a catalog has its own, and the resolver
     * must not know which.
     */
    private function findSubject(Subject $subject, string $slug): ?Model
    {
        /** @var Builder<Model> $query */
        $query = $subject->modelClass::query()->where($subject->slugColumn, $slug);

        $scope = $subject->visibilityScope;

        if ($scope !== null) {
            $query->{$scope}();
        }

        return $query->first();
    }

    /**
     * The canonical address of the entity that was found, rebuilt from the very
     * same declaration. A concert opened under the wrong series is a different
     * address and not the concert; a differently cased slug is one too, because
     * a database collation may well have matched it.
     *
     * A placeholder the subject declares no accessor for keeps the requested
     * value: it is context the declaration cannot verify, and inventing a
     * miss out of it would break every address the type describes.
     *
     * A value the template refuses to put into a segment leaves no canonical
     * address at all, and an address that cannot be rebuilt is a miss.
     *
     * @param  array<string, string>  $values  placeholder values of the requested address
     */
    private function canonicalPath(
        PathPattern $pattern,
        Subject $subject,
        Model $entity,
        array $values,
        string $subjectPlaceholder,
    ): ?string {
        $canonical = [];

        foreach ($values as $placeholder => $value) {
            $accessor = $placeholder === $subjectPlaceholder
                ? $subject->slugColumn
                : ($subject->context[$placeholder] ?? null);

            if ($accessor === null) {
                $canonical[$placeholder] = $value;

                continue;
            }

            $actual = data_get($entity, $accessor);

            $canonical[$placeholder] = is_scalar($actual) ? (string) $actual : '';
        }

        return $pattern->build($canonical);
    }

    /**
     * The single active template of a type; a second one cannot be created.
     */
    private function templateOf(PageType $type): ?CmsPage
    {
        return PageModel::query()
            ->where('type', $type->key())
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }
}
