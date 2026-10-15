<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Http\Resources;

use Ewk\Cms\Blocks\Links\LinkResolverRegistry;
use Ewk\Cms\Blocks\Models\CmsBlock;
use Ewk\Cms\Blocks\Schema\BlockSchema;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Contracts\Translation\Translator;
use Illuminate\Http\Request;

/**
 * Default resource for a block whose payload follows its schema: every
 * declared field converts its stored value into the frontend value.
 *
 * The requested language is the application locale — resolving it from the
 * `Accept-Language` header is the job of the application middleware, not of
 * this package. The fallback language comes from the language catalog.
 *
 * Keys stored under names the schema no longer declares are ignored here and
 * left untouched in the database.
 *
 * Open for extension by a block whose payload is its fields plus something
 * the core computes, {@see ColumnsBlockResource} being the first case.
 */
class SchemaBlockResource extends BlockResource
{
    /**
     * The declaration, the catalog, the resolver of links and the translator
     * are `protected` rather than `private`: a subclass that adds something
     * the core cannot compute — the order of a list, an address it assembles
     * itself — reads them here instead of keeping a second copy of the same
     * constructor arguments under a second name.
     */
    public function __construct(
        CmsBlock $block,
        protected readonly BlockSchema $schema,
        protected readonly LanguageCatalog $catalog,
        protected readonly LinkResolverRegistry $linkResolvers,
        protected readonly Translator $translator,
    ) {
        parent::__construct($block);
    }

    protected function toData(Request $request): array
    {
        $content = $this->block->content ?? [];
        $locale = $this->translator->getLocale();
        $fallbackLocale = $this->catalog->defaultLocale();

        $data = [];

        foreach ($this->schema->fields() as $field) {
            $data[$field->name()] = $field->toPayload(
                $content[$field->name()] ?? null,
                $locale,
                $fallbackLocale,
                $this->linkResolvers,
            );
        }

        return $data;
    }
}
