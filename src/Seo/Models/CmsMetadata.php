<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Models;

use Ewk\Cms\Blocks\Localization\LocalizedValueResolver;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Media\Services\MediaUsageRegistrar;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * What an editor fills in by hand for one owner. Nothing here is generated: the
 * only automatic thing in the package is the fallback computed when the
 * document is handed out, and it is not written back into this row.
 *
 * Translatable fields are language dictionaries, `{"ru": "…", "en": "…"}`, read
 * with the one reading order of the CMS.
 *
 * @property int $id
 * @property string $model_type
 * @property int $model_id
 * @property array<string, string>|null $title
 * @property array<string, string>|null $description
 * @property array<string, string>|null $og_title
 * @property array<string, string>|null $og_description
 * @property string|null $og_type
 * @property array<string, int>|null $og_image
 * @property string|null $tw_card
 * @property array<string, string>|null $tw_title
 * @property array<string, string>|null $tw_description
 * @property array<string, int>|null $tw_image
 * @property bool $robots_index
 * @property bool $robots_follow
 * @property string|null $canonical
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $model
 */
final class CmsMetadata extends Model
{
    /**
     * What the polymorphic columns of the CMS store for a metadata row. An
     * alias instead of the class name keeps the rows readable and survives a
     * renaming of the model.
     */
    public const string MORPH_ALIAS = 'cms_metadata';

    /**
     * The columns holding a dictionary of languages. Written out because
     * {@see self::putTranslation()} refuses anything else: a typo in the name
     * of a column would silently write a translation nobody reads.
     *
     * @var list<string>
     */
    public const array TRANSLATABLE = [
        'title',
        'description',
        'og_title',
        'og_description',
        'tw_title',
        'tw_description',
    ];

    protected $fillable = [
        'model_type',
        'model_id',
        'title',
        'description',
        'og_title',
        'og_description',
        'og_type',
        'og_image',
        'tw_card',
        'tw_title',
        'tw_description',
        'tw_image',
        'robots_index',
        'robots_follow',
        'canonical',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'title' => 'array',
        'description' => 'array',
        'og_title' => 'array',
        'og_description' => 'array',
        'tw_title' => 'array',
        'tw_description' => 'array',
        'og_image' => 'array',
        'tw_image' => 'array',
        'model_id' => 'integer',
        'robots_index' => 'boolean',
        'robots_follow' => 'boolean',
    ];

    /**
     * The two columns that point at the library, each a dictionary of languages
     * holding the key of a record, `{"ru": 12, "en": 15}`: a language version
     * is shared on its own, and the words on a picture are words of one
     * language. They are registered as usages one group each, the path being
     * `og_image.ru`, so that an admin listing where a file is used names the
     * card it belongs to and not just «метаданные».
     *
     * @var list<string>
     */
    public const array IMAGE_COLUMNS = [
        'og_image',
        'tw_image',
    ];

    protected static function booted(): void
    {
        // Metadata uses files exactly the way a block does, and the accounting
        // of what is used lives with whoever holds the reference: without this
        // a picture of a card could be deleted out of the library and the card
        // would point at nothing.
        static::saved(static function (self $metadata): void {
            $metadata->syncImageUsages();
        });

        static::deleted(static function (self $metadata): void {
            $metadata->releaseImageUsages();
        });
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Find the row of one owner without going through the owner, which is
     * what a reader does when the model of the installation does not use
     * {@see \Ewk\Cms\Seo\Concerns\HasSeoMetadata}.
     */
    public static function of(Model $owner): ?self
    {
        return self::query()
            ->where('model_type', $owner->getMorphClass())
            ->where('model_id', $owner->getKey())
            ->first();
    }

    public function titleFor(string $locale, string $default): ?string
    {
        return $this->localized($this->title, $locale, $default);
    }

    public function descriptionFor(string $locale, string $default): ?string
    {
        return $this->localized($this->description, $locale, $default);
    }

    public function ogTitleFor(string $locale, string $default): ?string
    {
        return $this->localized($this->og_title, $locale, $default);
    }

    public function ogDescriptionFor(string $locale, string $default): ?string
    {
        return $this->localized($this->og_description, $locale, $default);
    }

    public function twTitleFor(string $locale, string $default): ?string
    {
        return $this->localized($this->tw_title, $locale, $default);
    }

    public function twDescriptionFor(string $locale, string $default): ?string
    {
        return $this->localized($this->tw_description, $locale, $default);
    }

    /**
     * The key of the picture of the Open Graph card on this language, read in
     * the same order as a text: the language asked for, then the default one.
     * The column carries no foreign key, so the key may name a record that has
     * been deleted; the reader treats that as "no picture" rather than as an
     * error.
     */
    public function ogImageFor(string $locale, string $default): ?int
    {
        return self::localizedKey($this->og_image, $locale, $default);
    }

    public function twImageFor(string $locale, string $default): ?int
    {
        return self::localizedKey($this->tw_image, $locale, $default);
    }

    /**
     * Write one language of one translatable value, the way an API that takes a
     * single locale does.
     *
     * An empty value removes the language instead of storing an empty string:
     * the document builder tells "not filled in" from "filled in with
     * nothing", and only the first of the two falls back.
     */
    public function putTranslation(string $attribute, string $locale, ?string $value): void
    {
        if (! in_array($attribute, self::TRANSLATABLE, true)) {
            throw new InvalidArgumentException(sprintf('`%s` is not a translatable column of cms_metadata.', $attribute));
        }

        $stored = $this->getAttribute($attribute);
        $dictionary = is_array($stored) ? $stored : [];
        $value = $value === null ? null : trim($value);

        if ($value === null || $value === '') {
            unset($dictionary[$locale]);
        } else {
            $dictionary[$locale] = $value;
        }

        $this->setAttribute($attribute, $dictionary === [] ? null : $dictionary);
    }

    /**
     * Put one language of one picture; `null` removes the language, so that it
     * falls back to the default one again.
     */
    public function putImage(string $attribute, string $locale, ?int $mediaId): void
    {
        if (! in_array($attribute, self::IMAGE_COLUMNS, true)) {
            throw new InvalidArgumentException(sprintf('`%s` is not a picture column of cms_metadata.', $attribute));
        }

        $stored = $this->getAttribute($attribute);
        $dictionary = is_array($stored) ? $stored : [];

        if ($mediaId === null) {
            unset($dictionary[$locale]);
        } else {
            $dictionary[$locale] = $mediaId;
        }

        $this->setAttribute($attribute, $dictionary === [] ? null : $dictionary);
    }

    private function syncImageUsages(): void
    {
        $container = Container::getInstance();

        if (! $container->bound(MediaUsageRegistrar::class)) {
            return;
        }

        $registrar = $container->make(MediaUsageRegistrar::class);
        $identified = $this->identifiedImages();

        foreach (self::IMAGE_COLUMNS as $column) {
            $registrar->sync($this->getMorphClass(), $this->id, $column, $identified[$column] ?? []);
        }
    }

    /**
     * The languages whose value answers a record of the library, by column.
     * The column carries no foreign key while `cms_media_usages.cms_media_id`
     * does, so a reference to a record that is gone has to be left out here:
     * written as a usage it would be a row the database refuses.
     *
     * @return array<string, array<string, int>>  column => `column.locale` => media id
     */
    private function identifiedImages(): array
    {
        $ids = [];
        $all = [];

        foreach (self::IMAGE_COLUMNS as $column) {
            $stored = $this->getAttribute($column);

            foreach (is_array($stored) ? $stored : [] as $locale => $id) {
                if (is_int($id)) {
                    $ids[$column][$column.'.'.$locale] = $id;
                    $all[$id] = $id;
                }
            }
        }

        if ($ids === []) {
            return [];
        }

        /** @var list<int> $existing */
        $existing = CmsMedia::query()
            ->whereIn('id', array_values($all))
            ->pluck('id')
            ->all();

        foreach ($ids as $column => $byLocale) {
            $ids[$column] = array_filter($byLocale, static fn (int $id): bool => in_array($id, $existing, true));
        }

        return $ids;
    }

    private function releaseImageUsages(): void
    {
        $container = Container::getInstance();

        if (! $container->bound(MediaUsageRegistrar::class)) {
            return;
        }

        $container->make(MediaUsageRegistrar::class)->release($this->getMorphClass(), $this->id);
    }

    /**
     * @param  array<array-key, mixed>|null  $stored
     */
    private static function localizedKey(?array $stored, string $locale, string $default): ?int
    {
        $id = $stored[$locale] ?? $stored[$default] ?? null;

        return is_int($id) ? $id : null;
    }

    /**
     * A missing key, an empty string and a missing dictionary all mean "not
     * filled in", and the document builder needs to tell that apart from an
     * empty string it would print into a tag — hence `null` rather than `''`.
     *
     * @param  array<array-key, mixed>|string|null  $stored
     */
    private function localized(array|string|null $stored, string $locale, string $default): ?string
    {
        $value = LocalizedValueResolver::resolve($stored, $locale, $default);

        return $value === '' ? null : $value;
    }
}
