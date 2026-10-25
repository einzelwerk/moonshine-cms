<?php

declare(strict_types=1);

namespace Ewk\Cms\Pages\OpenApi;

use Ewk\Cms\Blocks\OpenApi\ComponentMerger;
use Ewk\Cms\Pages\Types\PageTypeRegistry;
use Illuminate\Container\Container;
use OpenApi\Analysis;
use OpenApi\Annotations as OA;

final class SubjectSchemasProcessor
{
    public function __invoke(Analysis $analysis): void
    {
        $openapi = $analysis->openapi;

        if (! $openapi instanceof OA\OpenApi) {
            return;
        }

        $container = Container::getInstance();

        if (! $container->bound(PageTypeRegistry::class)) {
            return;
        }

        /** @var PageTypeRegistry $types */
        $types = $container->make(PageTypeRegistry::class);

        $generator = new SubjectSchemaGenerator($types);
        $components = ComponentMerger::components($openapi);

        ComponentMerger::schemas($components, $generator->subjectSchemas());
        ComponentMerger::fallbackSchemas($components, [$generator->dataSchema()]);
    }
}
