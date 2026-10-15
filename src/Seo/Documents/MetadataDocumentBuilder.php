<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Documents;

use Ewk\Cms\Media\Enums\MediaVariantState;
use Ewk\Cms\Media\Models\CmsMedia;
use Ewk\Cms\Media\Models\CmsMediaVariant;
use Ewk\Cms\Seo\Contracts\ProvidesSeoFallbacks;
use Ewk\Cms\Seo\Models\CmsMetadata;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * The metadata of one page in the shape of `Metadata` of Next: camelCase keys,
 * addresses as strings, absolute and with the language in them. The frontend
 * puts this object into `generateMetadata` without rework.
 *
 * The fallbacks — a title of the owner, a picture of the owner, the Open Graph
 * values for Twitter — are computed here, at the moment the document is handed
 * out, and are never written back: a materialised title goes stale the first
 * time the entity is renamed.
 */
final readonly class MetadataDocumentBuilder
{
    public function __construct(
        private CanonicalUrl $canonical,
        private Repository $config,
        private UrlGenerator $urls,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(SeoContext $context): array
    {
        $metadata = self::metadataOf($context->owner);
        $locale = $context->locale;
        $default = $context->defaultLanguage;

        $title = $metadata?->titleFor($locale, $default) ?? self::fallbackTitle($context);

        // No fallback for the description on purpose. `null` and not an empty
        // string: an empty description tag is worse than none.
        $description = $metadata?->descriptionFor($locale, $default);

        $ogTitle = $metadata?->ogTitleFor($locale, $default) ?? $title;
        $ogDescription = $metadata?->ogDescriptionFor($locale, $default) ?? $description;
        // The picture is read by language like the texts beside it: the words
        // on a card are words of one language.
        $ogImage = $this->image($metadata?->ogImageFor($locale, $default)) ?? self::fallbackImage($context);

        // Twitter takes the Open Graph values, the picture included.
        $twitterTitle = $metadata?->twTitleFor($locale, $default) ?? $ogTitle;
        $twitterDescription = $metadata?->twDescriptionFor($locale, $default) ?? $ogDescription;
        $twitterImage = $this->image($metadata?->twImageFor($locale, $default)) ?? $ogImage;

        return [
            'title' => $title,
            'description' => $description,
            'alternates' => [
                'canonical' => $this->canonical->build($context, $metadata?->canonical),
                'languages' => $this->languages($context),
            ],
            'openGraph' => [
                'title' => $ogTitle,
                'description' => $ogDescription,
                'type' => self::nonEmpty($metadata?->og_type) ?? 'website',
                'images' => $ogImage === null ? [] : [self::imagePayload($ogImage)],
            ],
            'twitter' => [
                'card' => self::nonEmpty($metadata?->tw_card) ?? ($twitterImage === null ? 'summary' : 'summary_large_image'),
                'title' => $twitterTitle,
                'description' => $twitterDescription,
                'images' => $twitterImage === null ? [] : [$twitterImage['url']],
            ],
            'robots' => self::robots($metadata),
        ];
    }

    /**
     * Indexing is allowed until an editor forbids it, so an owner without a row
     * is indexed.
     *
     * @return array{index: bool, follow: bool}
     */
    private static function robots(?CmsMetadata $metadata): array
    {
        return $metadata === null
            ? ['index' => true, 'follow' => true]
            : ['index' => $metadata->robots_index, 'follow' => $metadata->robots_follow];
    }

    /**
     * The set is built from the languages of the catalog, because a published
     * page is available on all of them at once. Every version lists itself and
     * `x-default` points at the default language: without that reciprocity a
     * search engine ignores the tags altogether.
     *
     * A page that is not published has no language versions at all — it does
     * not exist for a visitor.
     *
     * @return array<string, string>
     */
    private function languages(SeoContext $context): array
    {
        if ($context->publishedAt === null || $context->languages === []) {
            return [];
        }

        $languages = [];

        foreach ($context->languages as $language) {
            $languages[$language] = $this->canonical->forLocale($context, $language);
        }

        if ($context->defaultLanguage !== '') {
            $languages['x-default'] = $this->canonical->forLocale($context, $context->defaultLanguage);
        }

        return $languages;
    }

    /**
     * The picture of a card is a reference to the media library. The configured
     * preset is used when its variant is ready; otherwise the original goes
     * into the tag, because a broken address is worse than a large picture.
     *
     * @return array{url: string, width: int|null, height: int|null}|null
     */
    private function image(?int $mediaId): ?array
    {
        if ($mediaId === null) {
            return null;
        }

        $media = CmsMedia::query()->with('variants')->find($mediaId);

        // The column carries no foreign key between packages, so a deleted
        // record reads as "no picture" rather than as an error.
        if (! $media instanceof CmsMedia) {
            return null;
        }

        $preset = self::nonEmpty($this->config->get('cms-seo.image_preset'));

        if ($preset !== null) {
            // Only this preset, never the "nearest ready variant" of
            // CmsMedia::resolvedVariantUrl(): the width and the height below
            // travel into the tag beside the address and have to belong to the
            // file at that address.
            $variant = $media->variants->firstWhere('preset', $preset);

            if ($variant instanceof CmsMediaVariant && $variant->state === MediaVariantState::READY && $variant->path !== null) {
                return [
                    'url' => $this->absolute($media->resolvedVariantUrl($preset)),
                    'width' => $variant->width,
                    'height' => $variant->height,
                ];
            }
        }

        return [
            'url' => $this->absolute($media->url()),
            'width' => $media->width,
            'height' => $media->height,
        ];
    }

    /**
     * The address of a file of the library as a tag on another host reads
     * it. A disk with an absolute `url` — a CDN, a bucket, `APP_URL/storage`
     * — already hands one out, and it passes through untouched. A disk
     * without one answers with a path such as `/storage/…`, which is a path
     * of this application and not of the public site, so it is completed
     * with `app.url`, the host that serves the files, and with the root the
     * URL generator knows where `app.url` is not configured.
     */
    private function absolute(string $url): string
    {
        if (preg_match('~^(?:[a-z][a-z0-9+.\-]*:)?//~i', $url) === 1) {
            return $url;
        }

        $root = $this->config->get('app.url');

        if (is_string($root) && trim($root) !== '') {
            return rtrim($root, '/').'/'.ltrim($url, '/');
        }

        return $this->urls->to($url);
    }

    /**
     * Dimensions travel only when they are known: Open Graph treats
     * `og:image:width` as a hint, and a wrong hint is worse than a missing
     * one.
     *
     * @param  array{url: string, width: int|null, height: int|null}  $image
     * @return array<string, mixed>
     */
    private static function imagePayload(array $image): array
    {
        $payload = ['url' => $image['url']];

        if ($image['width'] !== null) {
            $payload['width'] = $image['width'];
        }

        if ($image['height'] !== null) {
            $payload['height'] = $image['height'];
        }

        return $payload;
    }

    /**
     * The row of the owner. A model using {@see \Ewk\Cms\Seo\Concerns\HasSeoMetadata}
     * is asked through its relation, so an eager-loaded row costs no query;
     * anything else is looked up by the polymorphic pair.
     */
    private static function metadataOf(Model $owner): ?CmsMetadata
    {
        if (method_exists($owner, 'seoMetadata')) {
            if ($owner->relationLoaded('seoMetadata')) {
                $loaded = $owner->getRelation('seoMetadata');

                return $loaded instanceof CmsMetadata ? $loaded : null;
            }

            $relation = $owner->seoMetadata();

            if ($relation instanceof MorphOne) {
                $found = $relation->first();

                return $found instanceof CmsMetadata ? $found : null;
            }
        }

        return CmsMetadata::of($owner);
    }

    /**
     * What the caller put into the context wins over the contract of the
     * owner: the name of a page of `cms_pages` can only arrive that way, see
     * {@see SeoContext}.
     */
    private static function fallbackTitle(SeoContext $context): ?string
    {
        $given = self::nonEmpty($context->fallbackTitle);

        if ($given !== null) {
            return $given;
        }

        $owner = $context->owner;

        return $owner instanceof ProvidesSeoFallbacks
            ? self::nonEmpty($owner->seoFallbackTitle($context->locale))
            : null;
    }

    /**
     * @return array{url: string, width: int|null, height: int|null}|null
     */
    private static function fallbackImage(SeoContext $context): ?array
    {
        $url = self::nonEmpty($context->fallbackImage);

        if ($url === null) {
            $owner = $context->owner;

            $url = $owner instanceof ProvidesSeoFallbacks
                ? self::nonEmpty($owner->seoFallbackImage($context->locale))
                : null;
        }

        return $url === null ? null : ['url' => $url, 'width' => null, 'height' => null];
    }

    private static function nonEmpty(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
