<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\OpenApi;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Container\Container;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;

/**
 * The swagger-php processor that puts the generated components of the CMS into
 * the document.
 *
 * A processor and not a scanned attribute carrier, because the set of block
 * types is a runtime fact: it lives in the registry, an application adds its
 * own types to it, and no amount of doc comments in this package could know
 * them. The generator is not asked for a mould either — handwritten block
 * schemas are not a source of truth here.
 *
 * The container is asked for the registry at the moment of the call and never
 * in the constructor: the processor is built in `config/l5-swagger.php` as a
 * plain `new`, while the configuration is read long before the application is
 * booted.
 *
 * Name clashes are an error, not a merge. Two definitions under one component
 * name mean two contracts under one type on the frontend, and the one that
 * wins would depend on the order the files happened to be scanned in. The one
 * exception is the short list of {@see CommonSchemas::fallbackSchemas()}: those
 * are names the CMS points at but does not own — `CmsResolveExtension` — and
 * there a declaration of the application is the answer and the placeholder is
 * only put in when nobody declared one.
 */
final class CmsSchemasProcessor
{
    public function __invoke(Analysis $analysis): void
    {
        $openapi = $analysis->openapi;

        if (! $openapi instanceof OA\OpenApi) {
            return;
        }

        $container = Container::getInstance();

        if (! $container->bound(BlockRegistryInterface::class)) {
            return;
        }

        /** @var BlockRegistryInterface $registry */
        $registry = $container->make(BlockRegistryInterface::class);

        /** @var LanguageCatalog $languages */
        $languages = $container->make(LanguageCatalog::class);

        $components = ComponentMerger::components($openapi);

        ComponentMerger::schemas($components, [
            ...CommonSchemas::schemas(),
            ...(new BlockSchemaGenerator($registry))->schemas(),
        ]);

        ComponentMerger::fallbackSchemas($components, CommonSchemas::fallbackSchemas());

        ComponentMerger::parameters($components, [CommonSchemas::acceptLanguage($languages)]);
    }
}
