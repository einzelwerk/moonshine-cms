<?php

declare(strict_types=1);

namespace Ewk\Cms\Blocks\Contracts;

use Illuminate\Http\Request;

/**
 * Marks a block resource that may have nothing to show for the data of the
 * request, and is then left out of the response instead of arriving empty.
 *
 * The judgement is made on the payload already built, so a block decides it
 * out of the very selection it has drawn and never asks the database twice.
 * Only the envelope disappears: the anchor of the block is collected out of
 * the tree before any payload is built, so a table of contents of the same
 * owner still lists it.
 */
interface OmitsWhenEmpty
{
    /**
     * @param  array<mixed>  $data  the `data` of the envelope
     */
    public function isEmpty(array $data, Request $request): bool;
}
