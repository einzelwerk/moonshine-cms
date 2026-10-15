<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\OpenApi;

use Ewk\Cms\Blocks\Contracts\BlockRegistryInterface;
use Ewk\Cms\Media\Localization\LanguageCatalog;
use Illuminate\Container\Container;
use LogicException;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Undefined;

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
 * are names the CMS points at but does not own — `CmsSubjectData` first of
 * all — and there a declaration of the application is the answer and the
 * placeholder is only put in when nobody declared one.
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

        if (Undefined::isDefault($openapi->components)) {
            $openapi->components = new OA\Components(['_context' => $openapi->_context]);
        }

        $components = $openapi->components;

        $this->mergeSchemas($components, [
            ...CommonSchemas::schemas(),
            ...(new BlockSchemaGenerator($registry))->schemas(),
        ]);

        $this->mergeFallbackSchemas($components, CommonSchemas::fallbackSchemas());

        $this->mergeParameters($components, [CommonSchemas::acceptLanguage($languages)]);
    }

    /**
     * @param  list<OA\Schema>  $schemas
     */
    private function mergeSchemas(OA\Components $components, array $schemas): void
    {
        if (Undefined::isDefault($components->schemas)) {
            $components->schemas = [];
        }

        $existing = [];

        foreach ($components->schemas as $schema) {
            if (! Undefined::isDefault($schema->schema)) {
                $existing[(string) $schema->schema] = $schema;
            }
        }

        foreach ($schemas as $schema) {
            $name = (string) $schema->schema;

            if (! isset($existing[$name])) {
                $components->schemas[] = $schema;
                $existing[$name] = $schema;

                continue;
            }

            self::assertSame('schemas', $name, $existing[$name], $schema);
        }
    }

    /**
     * The components an installation may own: a placeholder is put in only
     * where nothing of that name is declared, and a declaration of the
     * application is left exactly as it is. This is the opposite of
     * {@see self::mergeSchemas()} on purpose — here a taken name is the
     * expected case and not a clash of two contracts.
     *
     * @param  list<OA\Schema>  $schemas
     */
    private function mergeFallbackSchemas(OA\Components $components, array $schemas): void
    {
        if (Undefined::isDefault($components->schemas)) {
            $components->schemas = [];
        }

        $declared = [];

        foreach ($components->schemas as $schema) {
            if (! Undefined::isDefault($schema->schema)) {
                $declared[(string) $schema->schema] = true;
            }
        }

        foreach ($schemas as $schema) {
            if (! isset($declared[(string) $schema->schema])) {
                $components->schemas[] = $schema;
            }
        }
    }

    /**
     * @param  list<OA\Parameter>  $parameters
     */
    private function mergeParameters(OA\Components $components, array $parameters): void
    {
        if (Undefined::isDefault($components->parameters)) {
            $components->parameters = [];
        }

        $existing = [];

        foreach ($components->parameters as $parameter) {
            if (! Undefined::isDefault($parameter->parameter)) {
                $existing[(string) $parameter->parameter] = $parameter;
            }
        }

        foreach ($parameters as $parameter) {
            $name = (string) $parameter->parameter;

            if (! isset($existing[$name])) {
                $components->parameters[] = $parameter;
                $existing[$name] = $parameter;

                continue;
            }

            self::assertSame('parameters', $name, $existing[$name], $parameter);
        }
    }

    /**
     * A component of this name is already in the document: identical is a
     * no-op, different is a conflict the generation must not paper over.
     */
    private static function assertSame(
        string $group,
        string $name,
        OA\AbstractAnnotation $existing,
        OA\AbstractAnnotation $generated,
    ): void {
        if (self::normalize($existing) === self::normalize($generated)) {
            return;
        }

        throw new LogicException(
            'The OpenAPI component [#/components/'.$group.'/'.$name.'] is already defined with another shape: '
            .'the generated schema of the CMS and a handwritten one cannot both own the name.'
        );
    }

    private static function normalize(OA\AbstractAnnotation $annotation): string
    {
        return json_encode($annotation->jsonSerialize(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ?: spl_object_hash($annotation);
    }
}
