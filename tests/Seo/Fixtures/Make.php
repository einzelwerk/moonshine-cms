<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Tests\Fixtures;

use DateTimeInterface;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Pages\Models\CmsPage;
use Ewk\Cms\Seo\Documents\MetadataDocumentBuilder;
use Ewk\Cms\Seo\Documents\SeoContext;
use Ewk\Cms\Seo\Models\CmsMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class Make
{
    /**
     * @param  array<string, string>  $name
     */
    public static function owner(array $name = ['ru' => 'Афиша', 'en' => 'Afisha'], ?string $cover = null): SeoOwner
    {
        $owner = new SeoOwner(['name' => $name, 'cover_url' => $cover]);
        $owner->save();

        return $owner;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function metadata(Model $owner, array $attributes = []): CmsMetadata
    {
        $metadata = new CmsMetadata($attributes);
        $metadata->model_type = $owner->getMorphClass();
        $metadata->model_id = (int) $owner->getKey();
        $metadata->save();

        return $metadata;
    }

    /**
     * @param  array<array-key, mixed>  $query
     * @param  list<string>  $filterKeys
     * @param  list<string>  $languages
     */
    public static function context(
        Model $owner,
        string $locale = 'ru',
        string $path = '/afisha',
        array $query = [],
        array $filterKeys = [],
        array $languages = ['ru', 'en', 'de'],
        string $defaultLanguage = 'ru',
        ?DateTimeInterface $publishedAt = new Carbon('2026-09-14 10:00:00'),
        ?string $fallbackTitle = null,
        ?string $fallbackImage = null,
    ): SeoContext {
        return new SeoContext(
            owner: $owner,
            locale: $locale,
            path: $path,
            query: $query,
            filterKeys: $filterKeys,
            languages: $languages,
            defaultLanguage: $defaultLanguage,
            publishedAt: $publishedAt,
            fallbackTitle: $fallbackTitle,
            fallbackImage: $fallbackImage,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function document(SeoContext $context): array
    {
        return app(MetadataDocumentBuilder::class)->build($context);
    }

    public static function media(string $path = 'cms/original.jpg', int $width = 2000, int $height = 1200): CmsMedia
    {
        return CmsMedia::query()->create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'original.jpg',
            'mime' => 'image/jpeg',
            'size' => 100500,
            'width' => $width,
            'height' => $height,
        ]);
    }

    public static function page(string $name = 'Контакты', string $slug = 'contacts'): CmsPage
    {
        return CmsPage::query()->create([
            'type' => 'page',
            'name' => ['ru' => $name],
            'slug' => $slug,
            'path' => '/'.$slug,
            'depth' => 0,
            'is_active' => true,
        ]);
    }
}
