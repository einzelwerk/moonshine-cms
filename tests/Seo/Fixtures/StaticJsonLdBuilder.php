<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\Tests\Fixtures;

use Ewk\Cms\Seo\Documents\SeoContext;
use Ewk\Cms\Seo\JsonLd\JsonLdBuilder;

final readonly class StaticJsonLdBuilder implements JsonLdBuilder
{
    /**
     * @param  list<array<string, mixed>>  $objects
     */
    public function __construct(private array $objects, private bool $supports = true)
    {
    }

    public function supports(SeoContext $context): bool
    {
        return $this->supports;
    }

    public function build(SeoContext $context): array
    {
        return $this->objects;
    }
}
