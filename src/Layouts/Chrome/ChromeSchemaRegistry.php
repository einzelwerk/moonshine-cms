<?php

declare(strict_types=1);

namespace Ewk\Cms\Layouts\Chrome;

/**
 * Which fields a header and a footer have in this installation. The package
 * ships none: the logo, the copyright and the payment icons of a concrete site
 * are exactly the knowledge a reusable package does not have, the same way
 * block types are registered by the application.
 *
 * A kind without a declaration is a configuration state, not a data error:
 * the stored content of such a row is left untouched on write and produces an
 * empty payload on read, because the schema is the contract of the payload.
 */
final class ChromeSchemaRegistry
{
    /** @var array<string, ChromeSchema> */
    private array $schemas = [];

    public function register(ChromeKind $kind, ChromeSchema $schema): void
    {
        $this->schemas[$kind->value] = $schema;
    }

    public function has(ChromeKind $kind): bool
    {
        return isset($this->schemas[$kind->value]);
    }

    public function schemaFor(ChromeKind $kind): ChromeSchema
    {
        return $this->schemas[$kind->value] ?? ChromeSchema::make();
    }
}
