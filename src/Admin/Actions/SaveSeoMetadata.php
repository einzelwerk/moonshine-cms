<?php

declare(strict_types=1);

namespace Ewk\MoonShineCms\Actions;

use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Seo\Enums\OpenGraphType;
use Ewk\Cms\Seo\Enums\TwitterCard;
use Ewk\Cms\Seo\Models\CmsMetadata;
use Ewk\MoonShineCms\MoonShine\Seo\SeoFields;
use Ewk\MoonShineCms\Support\ModelKey;
use Illuminate\Database\Eloquent\Model;

/**
 * The write of the metadata tab.
 *
 * It is not left to the generic apply of the panel for the same reason {@see
 * SaveChrome} is not: the value does not belong to the edited record at all —
 * it lives in a row of `cms_metadata` keyed by the polymorphic pair. A picture
 * is a reference by the time it arrives, uploaded when it was chosen.
 *
 * Nothing is generated here. An empty value removes the language rather than
 * storing an empty string, because the document builder tells "not filled in"
 * from "filled in with nothing" and only the first of the two falls back.
 */
final readonly class SaveSeoMetadata
{
    public function __construct(private LanguageCatalog $catalog)
    {
    }

    /**
     * @param  array<array-key, mixed>  $input  the submitted form
     */
    public function __invoke(Model $owner, array $input): void
    {
        $submitted = $input[SeoFields::PREFIX] ?? null;

        // No tab in the form means no metadata in this request: a resource
        // without the tab must not have its row wiped by a save.
        if (! is_array($submitted)) {
            return;
        }

        $metadata = self::rowOf($owner);

        foreach (CmsMetadata::TRANSLATABLE as $attribute) {
            if (array_key_exists($attribute, $submitted)) {
                $this->writeDictionary($metadata, $attribute, $submitted[$attribute]);
            }
        }

        if (array_key_exists('og_type', $submitted)) {
            $metadata->og_type = self::oneOf($submitted['og_type'], OpenGraphType::values());
        }

        if (array_key_exists('tw_card', $submitted)) {
            $metadata->tw_card = self::oneOf($submitted['tw_card'], TwitterCard::values());
        }

        if (array_key_exists('canonical', $submitted)) {
            $metadata->canonical = self::text($submitted['canonical']);
        }

        // Indexing is allowed until an editor forbids it, so a submission that
        // carries no switch at all reads as "allowed" and not as "left alone".
        $metadata->robots_index = self::flag($submitted['robots_index'] ?? null);
        $metadata->robots_follow = self::flag($submitted['robots_follow'] ?? null);

        foreach (CmsMetadata::IMAGE_COLUMNS as $attribute) {
            if (array_key_exists($attribute, $submitted)) {
                $this->writeImages($metadata, $attribute, $submitted[$attribute]);
            }
        }

        // A record whose tab was opened and left empty gets no row: an empty
        // row of metadata says nothing the absence of one does not.
        if (! $metadata->exists && ! self::carriesAnything($metadata)) {
            return;
        }

        $metadata->save();
    }

    /**
     * A language the catalog does not declare has no place in the row: the
     * form only ever shows the declared ones, so anything else is junk.
     */
    private function writeDictionary(CmsMetadata $metadata, string $attribute, mixed $submitted): void
    {
        $values = is_array($submitted) ? $submitted : [];

        foreach ($this->catalog->locales() as $locale) {
            $value = $values[$locale] ?? null;

            $metadata->putTranslation($attribute, $locale, is_string($value) ? $value : null);
        }
    }

    /**
     * One picture per language, `{"ru": {"id": "<uuid>"}}` as the pickers
     * submit it. A language the form did not send is left as it is, and a
     * language sent empty is removed, so it falls back to the default one.
     */
    private function writeImages(CmsMetadata $metadata, string $attribute, mixed $submitted): void
    {
        if (! is_array($submitted)) {
            return;
        }

        foreach ($this->catalog->locales() as $locale) {
            if (array_key_exists($locale, $submitted)) {
                $metadata->putImage($attribute, $locale, self::image($submitted[$locale]));
            }
        }
    }

    /**
     * The column stores the key of the record and the picker speaks uuids. The
     * column carries no foreign key into the media library, so a reference to a
     * record that is gone reads as no picture.
     */
    private static function image(mixed $submitted): ?int
    {
        if (! is_array($submitted)) {
            return null;
        }

        $uuid = $submitted['id'] ?? null;

        return is_string($uuid) && $uuid !== '' ? self::keyOf($uuid) : null;
    }

    private static function keyOf(string $uuid): ?int
    {
        $media = CmsMedia::query()->where('uuid', $uuid)->first();

        return $media instanceof CmsMedia ? $media->id : null;
    }

    private static function rowOf(Model $owner): CmsMetadata
    {
        $metadata = CmsMetadata::of($owner) ?? new CmsMetadata();

        $metadata->model_type = $owner->getMorphClass();
        $metadata->model_id = (int) ModelKey::of($owner);

        return $metadata;
    }

    /**
     * @param  list<string>  $allowed
     */
    private static function oneOf(mixed $value, array $allowed): ?string
    {
        $value = self::text($value);

        return $value !== null && in_array($value, $allowed, true) ? $value : null;
    }

    private static function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private static function flag(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private static function carriesAnything(CmsMetadata $metadata): bool
    {
        foreach (CmsMetadata::TRANSLATABLE as $attribute) {
            if ($metadata->getAttribute($attribute) !== null) {
                return true;
            }
        }

        return $metadata->og_type !== null
            || $metadata->tw_card !== null
            || $metadata->canonical !== null
            || $metadata->og_image !== null
            || $metadata->tw_image !== null
            || ! $metadata->robots_index
            || ! $metadata->robots_follow;
    }
}
