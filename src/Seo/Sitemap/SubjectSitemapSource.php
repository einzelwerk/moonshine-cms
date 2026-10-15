<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Sitemap;

use Closure;
use DateTimeInterface;
use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Pages\Support\PageModel;
use Ewk\Cms\Pages\Support\SubjectPath;
use Ewk\Cms\Pages\Types\PageType;
use Ewk\Cms\Pages\Types\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The entities of one template page type: every tour, every event. The set
 * starts where the resolver starts, from the visibility criterion the type
 * declares, so the sitemap never lists an address that answers 404.
 *
 * What may be opened is not always what should be listed: an event that took
 * place last year still opens, a hidden record opens by a direct link. The
 * installation narrows the set with {@see SitemapRegistry::narrow()}, the
 * package knows neither rule.
 *
 * A type whose template is switched off or has never been published answers
 * nothing at all: the resolver has no content for any of its addresses.
 *
 * The date is the latest of the three things a visitor of the page reads: the
 * record itself, its metadata, and the published template every record of
 * the type is drawn by.
 */
final readonly class SubjectSitemapSource implements SitemapSource
{
    /**
     * @param  list<Closure(Builder<Model>): void>  $narrowing
     */
    public function __construct(
        private PageType $type,
        private array $narrowing = [],
    ) {
    }

    public function key(): string
    {
        return $this->type->key();
    }

    public function count(): int
    {
        $query = $this->query();

        if ($query === null || ! $this->templatePublication() instanceof CmsVersion) {
            return 0;
        }

        return $query->count();
    }

    public function items(int $offset, int $limit): array
    {
        $query = $this->query();
        $template = $this->templatePublication();

        if ($query === null || ! $template instanceof CmsVersion) {
            return [];
        }

        $model = $query->getModel();

        $records = $query
            ->with($this->relations())
            ->orderBy($model->getQualifiedKeyName())
            ->offset($offset)
            ->limit($limit)
            ->get();

        /** @var list<int|string> $ids */
        $ids = $records->modelKeys();
        $edited = Moments::metadata($model->getMorphClass(), $ids);

        $items = [];

        foreach ($records as $record) {
            $path = SubjectPath::of($this->type, $record);

            if ($path === null) {
                continue;
            }

            $key = $record->getKey();

            $items[] = new SitemapItem($path, SitemapItem::latest(
                self::updatedAt($record),
                is_int($key) || is_string($key) ? ($edited[$key] ?? null) : null,
                $template->published_at,
            ));
        }

        return $items;
    }

    /**
     * @return Builder<Model>|null
     */
    private function query(): ?Builder
    {
        $subject = $this->type->subject();

        if (! $subject instanceof Subject || ! $this->type->isTemplate()) {
            return null;
        }

        /** @var Builder<Model> $query */
        $query = $subject->modelClass::query();

        // Required by the registry of types, so a declaration without it
        // never gets this far.
        if ($subject->visibilityScope !== null) {
            $query->{$subject->visibilityScope}();
        }

        foreach ($this->narrowing as $narrow) {
            $narrow($query);
        }

        Indexable::apply($query);

        return $query;
    }

    /**
     * The snapshot of the single active template of the type, the one the
     * resolver draws every record of it with.
     */
    private function templatePublication(): ?CmsVersion
    {
        $template = PageModel::query()
            ->where('type', $this->type->key())
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $template instanceof CmsPage) {
            return null;
        }

        return CmsVersion::query()
            ->where('owner_type', CmsPage::MORPH_ALIAS)
            ->where('owner_id', $template->id)
            ->first();
    }

    /**
     * The relations the path template reads through: `tour.slug` of an event
     * loads the tour of every record of the slice in one query instead of one
     * per record.
     *
     * @return list<string>
     */
    private function relations(): array
    {
        $relations = [];

        foreach ($this->type->subject()->context ?? [] as $accessor) {
            $dot = strrpos($accessor, '.');

            if ($dot !== false) {
                $relations[] = substr($accessor, 0, $dot);
            }
        }

        return array_values(array_unique($relations));
    }

    private static function updatedAt(Model $record): ?DateTimeInterface
    {
        if (! $record->usesTimestamps()) {
            return null;
        }

        $column = $record->getUpdatedAtColumn();

        if ($column === null) {
            return null;
        }

        $value = $record->getAttribute($column);

        return $value instanceof DateTimeInterface ? $value : null;
    }
}
