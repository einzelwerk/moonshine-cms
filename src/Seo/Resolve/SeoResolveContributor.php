<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Resolve;

use Ewk\Cms\Blocks\Localization\LocalizedValueResolver;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Ewk\Cms\Pages\Http\Resolve\ResolveContributor;
use Ewk\Cms\Pages\Http\Resolve\ResolveDocument;
use Ewk\Cms\Seo\Documents\MetadataDocumentBuilder;
use Ewk\Cms\Seo\Documents\SeoContext;
use Ewk\Cms\Seo\JsonLd\JsonLdRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * How metadata and structured data reach the answer of the resolver: the pages
 * may not depend on the SEO part, so the resolver writes its own body and then
 * lets a contributor add ours.
 *
 * Everything the document needs is read off the document and never guessed
 * from the payload: the resolved address, the published version, the
 * normalized parameters of the blocks and the language of the request.
 */
final readonly class SeoResolveContributor implements ResolveContributor
{
    public function __construct(
        private MetadataDocumentBuilder $documents,
        private JsonLdRegistry $jsonLd,
        private LanguageCatalog $languages,
    ) {
    }

    public function contribute(ResolveDocument $document, Request $request): void
    {
        $context = $this->context($document);

        if (! $context instanceof SeoContext) {
            return;
        }

        $document->set('metadata', $this->documents->build($context));
        $document->set('json_ld', $this->jsonLd->build($context));
    }

    /**
     * The owner of the metadata is the subject of a template address when
     * there is one, and the page itself otherwise: the title of `/tours/nutcracker`
     * belongs to the tour, not to the one template page every tour is drawn by.
     */
    private function context(ResolveDocument $document): ?SeoContext
    {
        $resolved = $document->resolved();
        $page = $resolved->page;
        $owner = $resolved->subject ?? $page;

        // The resolver hands out a document only for a page it found, so this
        // is unreachable in practice; a contributor that would write metadata
        // of nothing is worse than one that writes none.
        if (! $owner instanceof Model) {
            return null;
        }

        $parameters = $document->parameters();

        return new SeoContext(
            owner: $owner,
            locale: $document->locale(),
            path: $resolved->path,
            query: $parameters->toQuery(),
            // Known limitation: parameters are declared per block type, so a
            // decorative selection (`manual`, `random`, `similar`) of a listing
            // block contributes filtering keys of its own even though a visitor
            // never sets them. They are excluded from the canonical address,
            // which is harmless.
            filterKeys: $parameters->filterQueryKeys(),
            languages: $this->languages->locales(),
            defaultLanguage: $this->languages->defaultLocale(),
            publishedAt: $document->version()?->published_at,
            // The name of a page can only arrive this way: the pages do not
            // depend on the SEO part, so their model does not implement
            // ProvidesSeoFallbacks, see SeoContext. A subject answers for
            // itself and is left to its own contract.
            fallbackTitle: $resolved->subject === null && $page !== null
                ? self::nonEmpty(LocalizedValueResolver::resolve(
                    $page->name,
                    $document->locale(),
                    $this->languages->defaultLocale(),
                ))
                : null,
            // No shared spare picture: the last line of defence stays with the
            // frontend.
            fallbackImage: null,
        );
    }

    private static function nonEmpty(string $value): ?string
    {
        return trim($value) === '' ? null : $value;
    }
}
