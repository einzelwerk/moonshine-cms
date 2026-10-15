<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Editor;

use Ewk\Cms\Blocks\Actions\PublishOwner;
use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Blocks\Fields\Field;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Models\CmsVersion;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Computes the publication indicator. The state is derived every time from
 * three facts: does a snapshot exist, is the owner switched on, and has
 * anything changed since the snapshot was written.
 *
 * Next to it sits the translation completeness hint: which languages are not
 * filled everywhere. It never blocks a save or a publication — publishing rolls
 * out every language and an empty value falls back.
 */
final readonly class PublicationStatusResolver
{
    public function __construct(
        private PublishOwner $publish,
        private BlockRegistryInterface $registry,
        private LanguageCatalog $catalog,
    ) {
    }

    public function resolve(Model $owner): PublicationState
    {
        $version = CmsVersion::query()
            ->where('owner_type', $owner->getMorphClass())
            ->where('owner_id', $owner->getKey())
            ->first();

        /** @var Collection<int, CmsBlock> $rows */
        $rows = CmsBlock::query()
            ->where('blockable_type', $owner->getMorphClass())
            ->where('blockable_id', $owner->getKey())
            ->get();

        return new PublicationState(
            status: $this->statusOf($owner, $version, $rows),
            publishedAt: $version?->published_at,
            number: $version instanceof CmsVersion ? $version->number : 0,
            incompleteLocales: $this->incompleteLocales($rows),
            problems: $this->publish->problemsOf($owner),
        );
    }

    /**
     * @param  Collection<int, CmsBlock>  $rows
     */
    private function statusOf(Model $owner, ?CmsVersion $version, Collection $rows): PublicationStatus
    {
        if (! $version instanceof CmsVersion) {
            return PublicationStatus::DRAFT;
        }

        if (! $this->isOwnerActive($owner)) {
            return PublicationStatus::UNPUBLISHED;
        }

        return $this->changedSince($owner, $rows, $version->published_at)
            ? PublicationStatus::PUBLISHED_AND_MODIFIED
            : PublicationStatus::PUBLISHED;
    }

    /**
     * An owner without an activity flag counts as switched on: activity is a
     * property of a page, not of the block mechanism.
     */
    private function isOwnerActive(Model $owner): bool
    {
        $active = $owner->getAttribute('is_active');

        return $active === null || (bool) $active;
    }

    /**
     * The modification mark is the one of the owner: every operation over the
     * blocks — a save, a move, a deletion — moves it, so two dates are enough
     * and a snapshot never has to be rebuilt for the indicator. A deletion in
     * particular leaves nothing among the live rows to compare against.
     *
     * The rows are still walked after it, for an owner that carries no mark
     * of its own: the scan can only add the state «modified», never take it
     * away.
     *
     * @param  Collection<int, CmsBlock>  $rows
     */
    private function changedSince(Model $owner, Collection $rows, ?Carbon $publishedAt): bool
    {
        if (! $publishedAt instanceof Carbon) {
            return true;
        }

        $ownerUpdated = $owner->getAttribute('updated_at');

        if ($ownerUpdated instanceof Carbon && $ownerUpdated->greaterThan($publishedAt)) {
            return true;
        }

        foreach ($rows as $row) {
            if ($row->updated_at instanceof Carbon && $row->updated_at->greaterThan($publishedAt)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Languages that are not filled everywhere a translatable field is
     * declared.
     *
     * @param  Collection<int, CmsBlock>  $rows
     * @return list<string>
     */
    private function incompleteLocales(Collection $rows): array
    {
        $incomplete = [];

        foreach ($this->catalog->locales() as $locale) {
            foreach ($rows as $row) {
                if (! $this->registry->has($row->type)) {
                    continue;
                }

                foreach ($this->registry->schemaFor($row->type)->fields() as $field) {
                    if (! $field->isTranslatable() || $this->isFilled($row, $field, $locale)) {
                        continue;
                    }

                    $incomplete[] = $locale;

                    continue 3;
                }
            }
        }

        return $incomplete;
    }

    private function isFilled(CmsBlock $row, Field $field, string $locale): bool
    {
        $content = $row->content ?? [];
        $value = $content[$field->name()] ?? null;

        if (! is_array($value)) {
            return false;
        }

        $localized = $value[$locale] ?? null;

        return is_string($localized) ? trim($localized) !== '' : $localized !== null;
    }
}
