<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\OpenApi;

use LogicException;
use OpenApi\Annotations as OA;
use OpenApi\Undefined;

final class ComponentMerger
{
    public static function components(OA\OpenApi $openapi): OA\Components
    {
        if (Undefined::isDefault($openapi->components)) {
            $openapi->components = new OA\Components(['_context' => $openapi->_context]);
        }

        if (Undefined::isDefault($openapi->components->schemas)) {
            $openapi->components->schemas = [];
        }

        return $openapi->components;
    }

    /**
     * @param  list<OA\Schema>  $schemas
     */
    public static function schemas(OA\Components $components, array $schemas): void
    {
        $existing = self::declared($components);

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
     * @param  list<OA\Schema>  $schemas
     */
    public static function fallbackSchemas(OA\Components $components, array $schemas): void
    {
        $declared = self::declared($components);

        foreach ($schemas as $schema) {
            if (! isset($declared[(string) $schema->schema])) {
                $components->schemas[] = $schema;
            }
        }
    }

    /**
     * @param  list<OA\Parameter>  $parameters
     */
    public static function parameters(OA\Components $components, array $parameters): void
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
     * @return array<string, OA\Schema>
     */
    private static function declared(OA\Components $components): array
    {
        if (Undefined::isDefault($components->schemas)) {
            $components->schemas = [];
        }

        $declared = [];

        foreach ($components->schemas as $schema) {
            if (! Undefined::isDefault($schema->schema)) {
                $declared[(string) $schema->schema] = $schema;
            }
        }

        return $declared;
    }

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
