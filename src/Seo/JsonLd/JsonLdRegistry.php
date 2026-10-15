<?php

declare(strict_types=1);

namespace Ewk\Cms\Seo\JsonLd;

use Ewk\Cms\Seo\Documents\SeoContext;

/**
 * The one extension mechanism of the CMS applied to structured data: the
 * application registers its builders in its provider, the core knows nothing
 * about them.
 */
final class JsonLdRegistry
{
    /** @var list<JsonLdBuilder> */
    private array $builders = [];

    public function register(JsonLdBuilder $builder): void
    {
        $this->builders[] = $builder;
    }

    /**
     * @return list<JsonLdBuilder>
     */
    public function all(): array
    {
        return $this->builders;
    }

    /**
     * Objects of every builder that claims the page, in registration order.
     *
     * @return list<array<string, mixed>>
     */
    public function build(SeoContext $context): array
    {
        $objects = [];

        foreach ($this->builders as $builder) {
            if (! $builder->supports($context)) {
                continue;
            }

            foreach ($builder->build($context) as $object) {
                $objects[] = $object;
            }
        }

        return $objects;
    }
}
