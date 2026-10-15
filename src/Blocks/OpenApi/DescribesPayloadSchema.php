<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\OpenApi;

use OpenApi\Annotations\Schema;

/**
 * A block resource that describes its own payload.
 *
 * The generator derives `data` from the fields of the schema, and for a block
 * whose resource returns the stored values that is the whole truth. A resource
 * that composes the payload out of a domain selection — a schedule, a grid of
 * events, a checkout widget — knows a shape the declaration cannot show, so it
 * hands the description over instead of letting the generator guess.
 *
 * The method is static on purpose: the generator walks the registry of types
 * and never has a block row to build a resource from.
 */
interface DescribesPayloadSchema
{
    /**
     * The `data` object of this block type. The generator names the component
     * itself, so the returned schema needs no `schema` of its own.
     */
    public static function payloadSchema(): Schema;
}
